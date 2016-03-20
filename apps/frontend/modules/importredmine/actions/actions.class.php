<?php

/**
 * importredmine actions.
 *
 * @package    sf_sandbox
 * @subpackage importredmine
 * @author     Your name here
 * @version    SVN: $Id: actions.class.php 2692 2006-11-15 21:03:55Z fabien $
 */
class importredmineActions extends sfActions
{
	private $maj_log = "maj_log.txt";
	const JOUR_TIME = 86400;
	const ERROR_LOG = "ERROR";
	
  /**
   * Renvoit la liste des erreurs survenues lors de la mise a jour du planning
   *
   */
  public function executeIndex()
  {
  	$this->page_name = 'Import Redmine';
  	$c = new Criteria();
	$c->add(FigerPeer::MOIS, date('Y') . "-" . date('m') . '-01');
	$this->isFix = (FigerPeer::doCount($c) > 0 ? true : false);
	$this->users_maj = "";
	
	if($fp = fopen($this->maj_log, 'r')) {
		$this->date_maj = fgets($fp);
		while(!feof($fp)) {
			$userName = fgets($fp);
			if(self::ERROR_LOG == trim(substr($userName, -1*(strlen(self::ERROR_LOG) + 1)))) {
				$this->users_maj .= '<span class="red">' . substr($userName, 0, -1*(strlen(self::ERROR_LOG) + 1)) . '</span> - ';
			}
			else {
				$this->users_maj .= $userName . ' - ';
			}
		}
		fclose($fp);
		
		if(!empty($this->users_maj)) { // Enleve le surplus de - du au saut de ligne
			$this->users_maj = substr($this->users_maj, 0, -2);
		}
	}
  }
  
  
  
  /**
   * Mise a jour du planning (appel manuel)
   * 
   */
  public function executeMajPlanning()
  {
	$this->_majPlanning();
	// Affichage des erreurs
	$this->redirect('importredmine/index');
  }
  
  
  
  /**
   * Mise a jour du planning (appel CRON)
   * 
   */
  public function executeMajPlanningCron() {
  	$this->_majPlanning();
  	return sfView::NONE;
  }
  
  
  
  /**
   * Sous-fonction pour la mise a jour du planning Saphir
   * 
   */
  private function _majPlanning() {
  	set_time_limit(0); // ligne ajoutée **
  	// Test si l'activite est figee
  	$c = new Criteria();
	$c->add(FigerPeer::MOIS, date('Y-m-01'));
	if(FigerPeer::doCount($c) > 0)
		return;
// Début modification **
	//Supprimer tous les éléments de la liste uo
	$d = new Criteria();
  	$d->add(ListeuoPeer::ID, 1, Criteria::GREATER_EQUAL);
  	$suppresion = ListeuoPeer::doDelete($d);
// Fin modification **		
	// Connexion a la base de donnees Redmine
	try {
  		$redmine = new RedmineWrapperMysql();
  		$datas = $redmine->getUserProjectInfos(date('Ym01'), date('Ymt'));
  		$duree_totale_reelle_user = $redmine->getUserTotalTimeLoggedWithoutAbsence(date('Ym01'), date('Ymt'));
  		$datas_absences = $redmine->getUserAbsences(date('Ym01'), date('Ymt'));
// Début modification **
  		// Charge toutes les US dans une variable
  		$aProject = $redmine->ChargerProjets();
// Fin modification **
  		$redmine->close_db();
	}
    catch (CustomMySQLException $e) {
  		$this->log($e->getMessage());
  	}
  	
  	$this->setFlash('hasNoError', true);

  	// Remplissage du planning Saphir 
  	if(!empty($datas) && !empty($duree_totale_reelle_user)) {  		
  		$year = date('Y');
	  	$month = date('m');
	  	$premierJourOuvre = $this->_prochainJourOuvre(mktime(0, 0, 0, $month , 0, $year));
	  	$dernierJourOuvre = $this->_dernierJourOuvreDuMois();
	  	$liste_utilisateurs = ''; // Liste des utilisateurs
	  	
	  	// Pour chaque utilisateur
	  	foreach ($datas as $mail => $data) {	
	  		
	  		// Recupere l'id de l'utilisateur via son mail
	  		$utilisateur = UtilisateurPeer::getUtilisateurByEmail($mail);
	  		if(!$utilisateur) {
	  			$this->log(ImportRedmineLog::UNKNOWN_USER, $mail);
	  			continue;
	  		}
	  		
	  		// Nettoyage du planning sur les imports Redmine
	  		$this->_nettoyagePlanning($utilisateur->getId(), date('Y-m-01'), date('Y-m-t'));
	  		
	  		$liste_utilisateurs .= "\n" . $utilisateur->getPrenom() . ' ' . $utilisateur->getNom();
	  		
	  		// Initialisation des variables statitques
	  		$coeff = 1; // Coefficient pour la mise a l'echelle des logs time
  			if($utilisateur->getNbheureJour() == 4) { // Presta, c'est 4 dans la base de donnees, correspondant au decoupage d'une journee
  				$coeff = $utilisateur->getNbheureJour()/7;
	  		}
	  		
	  		// Gestion des jours d'absence
	  		if(array_key_exists($mail, $datas_absences)) { // L'utilisateur a des absences
	  			// Initialisation des variables
	  			$absences = $datas_absences[$mail];
	  			$availableTime = 0; // Temps disponible dans la journee  		
	  			foreach ($absences as $absence) {
	  				$projet = ProjetPeer::getProjetByCode(utf8_encode(Constants::CODE_ABSENCE));

	  				$projectTimeLeft = round($absence['heure_totale'] * $coeff); // Temps projet restant a placer
			  		if($projectTimeLeft == 0) { // Pour les projets de duree inferieure a un jour, on arrondit a l'unite
			  			$projectTimeLeft = 1;
			  		}
			  		
			  		$spentDay = explode("-", $absence['spent_on']); // AAAA-MM-JJ
			  		$day = $spentDay[2];
			  		if($projectTimeLeft <= $utilisateur->getNbheureJour()) { // Mois d'un jour d'absence
			  			$this->saveActivite($utilisateur->getId(), $projet->getId(), mktime(0, 0, 0, $month , $day, $year), $projectTimeLeft);
			  		}
			  		else {
				  		while(($day <= $dernierJourOuvre) && ($projectTimeLeft > 0)) {
					  		if($this->_isJourNonOuvre(mktime(0, 0, 0, $month , $day, $year))) {
				  				$day++;
				  				continue;
				  			}
				  			
					  		if($availableTime == 0) { // On est passe a un autre jour, on reinitialise le temps disponible
					  			$availableTime = $utilisateur->getNbheureJour();
					  			
					  			// Presence d'activites (manuel ou absence par exemple) ?
					  			$c2 = new Criteria();
					  			$c2->add(ActiviteRealPeer::UTILISATEUR_ID, $utilisateur->getId());
					  			$c2->addAnd(ActiviteRealPeer::JOUR, date('Y-m-' . $day));
					  			$activites = ActiviteRealPeer::doSelect($c2);
					  			foreach ($activites as $activite) {
					  				$availableTime -= $activite->getDuree();
					  			}
					  			
					  			if($availableTime <= 0) { // Plus de place, on passe au prochain jour
					  				$day++;
				  					continue;
					  			}
				  			}
				  			
					  		if($projectTimeLeft >= $availableTime) {
			  					$this->saveActivite($utilisateur->getId(), $projet->getId(), mktime(0, 0, 0, $month , $day, $year), $availableTime);
			  					$projectTimeLeft -= $availableTime;
			  					$availableTime = 0; // On va passer a un autre jour
			  					$day++;
			  				}
			  				else {
			  					$this->saveActivite($utilisateur->getId(), $projet->getId(), mktime(0, 0, 0, $month , $day, $year), $projectTimeLeft);
			  					$availableTime -= $projectTimeLeft;
			  					$projectTimeLeft = 0; // On va passer a un autre projet
			  				}
				  		}
			  		}
	  			}
	  		}
	  		
	  		
	  		// Initialisation des variables
	  		$availableTime = 0; // Temps disponible dans la journee
		  	$duree_totale_user = 0; // Somme des durees de tous les projets a la fin du remplissage, pour la gestion des arrondis
		  	$max_duree_projet = 0; // Duree maximale en heures entre les projets du collaborateur
		  	$projet_principal_id = -1; // Projet principal calcule sur la duree maximale	  		
	  		$day = $premierJourOuvre;
	  		
	  		// Pour chaque projet
	  		foreach ($data as $p) {
	  			if($p['project_code_saphir'] != Constants::CODE_ABSENCE) {
		  			// Recupere le projet via le code Saphir
		  			if(empty($p['user_code_saphir'])) {
			  			$projet = ProjetPeer::getProjetByCode($p['project_code_saphir']);
		  			}
		  			else {
		  				$projet = ProjetPeer::getProjetByCode($p['user_code_saphir']);
		  			}
		  			
		  			if(!$projet) {
		  				if(empty($p['user_code_saphir'])) {
			  				if(empty($p['project_code_saphir'])) {
			  					$this->log(ImportRedmineLog::UNKNOWN_CODE . ' pour le projet "' . $p['nom_projet'] . '"', $mail, "");
			  				}
			  				else {
				  				$this->log(ImportRedmineLog::UNKNOWN_CODE, $mail, $p['project_code_saphir']);
			  				}
		  				}
		  				else {
		  					$this->log(ImportRedmineLog::UNKNOWN_CODE, $mail, $p['user_code_saphir']);
		  				}
		  				$liste_utilisateurs .= ' ' . self::ERROR_LOG;
		  				$this->setFlash('hasNoError', false);
			  			continue;
			  		}
			  		
			  		// Projet principal ?
			  		if($p['heure_totale'] > $max_duree_projet) {
			  			$max_duree_projet = $p['heure_totale'];
			  			$projet_principal_id = $projet->getId();
			  		}
			  		
			  		$projectTimeLeft = round($p['heure_totale'] * $coeff); // Temps projet restant a placer
			  		if($projectTimeLeft == 0) { // Pour les projets de duree inferieure a un jour, on arrondit a l'unite
			  			$projectTimeLeft = 1;
			  		}
			  		
			  		if(sfConfig::get('sf_logging_enabled')) { // Permet de voir les eventuels problemes d'arrondis
			  			$this->logMessage(
			  				'{sfView} [Import Redmine] Utilisateur en cours : ' . 
			  				$utilisateur->getPrenom() . ' ' . 
			  				$utilisateur->getNom() . ' ' . 
			  				$projet->getNom() . ' ' . 
			  				$projectTimeLeft, 
			  				'debug');
			  		}
			  		
			  		// Remplissage par jour
			  		while(($day <= $dernierJourOuvre) && ($projectTimeLeft > 0)) {
			  			if($this->_isJourNonOuvre(mktime(0, 0, 0, $month , $day, $year))) {
			  				$day++;
			  				continue;
			  			}
			  			
			  				
			  			/*
			  			 * Calcul de la disponibilite du jour
			  			 * Il se peut qu'il y ait un ajout manuel dans le planning
			  			 */
			  			if($availableTime == 0) { // On est passe a un autre jour, on reinitialise le temps disponible
				  			$availableTime = $utilisateur->getNbheureJour();
				  			
				  			// Presence d'activites (manuel ou absence par exemple) ?
				  			$c2 = new Criteria();
				  			$c2->add(ActiviteRealPeer::UTILISATEUR_ID, $utilisateur->getId());
				  			$c2->addAnd(ActiviteRealPeer::JOUR, date('Y-m-' . $day));
				  			$activites = ActiviteRealPeer::doSelect($c2);
				  			foreach ($activites as $activite) {
				  				$availableTime -= $activite->getDuree();
				  			}
				  			
				  			if($availableTime <= 0) { // Plus de place, on passe au prochain jour
				  				$day++;
			  					continue;
				  			}
			  			}
			  			
		  				
		  				/*
		  				 * Remplissage du planning
		  				 * Le projet peut durer plus d'une journee ou moins
		  				 */
			  			if($projectTimeLeft >= $availableTime) {
		  					$this->saveActivite($utilisateur->getId(), $projet->getId(), mktime(0, 0, 0, $month , $day, $year), $availableTime);
		  					$duree_totale_user += $availableTime;
		  					$projectTimeLeft -= $availableTime;
		  					$availableTime = 0; // On va passer a un autre jour
		  					$day++;
		  				}
		  				else {
		  					$this->saveActivite($utilisateur->getId(), $projet->getId(), mktime(0, 0, 0, $month , $day, $year), $projectTimeLeft);
		  					$duree_totale_user += $projectTimeLeft;
		  					$availableTime -= $projectTimeLeft;
		  					$projectTimeLeft = 0; // On va passer a un autre projet
		  				}
			  		}
	  			}
	  		}
	  		
	  		
	  		/*
	  		 * Gestion des erreurs d'arrondi
	  		 * On attribue les heures qui manquent au projet principal, en excluant "le projet" Absences-Cong�s
	  		 * Ne se fait que sur un jour non rempli
	  		 */
	  		$duree_manquante = round($duree_totale_reelle_user[$mail]*$coeff) - $duree_totale_user;
	  		if(($availableTime > 0) && ($day <= $dernierJourOuvre) && ($duree_manquante > 0)) {
	  			if($duree_manquante >= $availableTime) {
  					$this->saveActivite($utilisateur->getId(), $projet_principal_id, mktime(0, 0, 0, $month , $day, $year), $availableTime);
  				}
  				else {
  					$this->saveActivite($utilisateur->getId(), $projet_principal_id, mktime(0, 0, 0, $month , $day, $year), $duree_manquante);
  				}
  				$this->log(ImportRedmineLog::MISS_HOURS, $mail);
	  		}
	  		
	  		if(($day > $dernierJourOuvre) && ($projectTimeLeft > 0)) { // Trop d'heures, pas de place sur le planning
	  			$this->log(ImportRedmineLog::OVER_LIMIT, $mail);
	  		}
	  	}
  		
	  	// Enregistrement des utilisateurs traites
		if($fp = fopen($this->maj_log, 'w')) {
			fputs($fp, date('d-m-Y H:i:s') . $liste_utilisateurs . "\n");
			fclose($fp);
		}
  	}
// Début modification **
	// Sauvegarde tous les projets dans la table listeuo de la BDD saphir2
	$con = Propel::getConnection(ListeuoPeer::DATABASE_NAME);

	$con->begin();
	
  foreach ($aProject as $travail){
  		/*$this->saveListeuo($con, $travail['nom'], utf8_encode($travail['sujet']), $travail['heure'], $travail['mail'], $travail['tyear'], $travail['tmonth']);
  		 * */
  	
  		 $this->SauvegardeListeUo($travail, $con);
  
  	}
  	$con->commit();



  		
	
	
  }

// 2e fonction de sauvegarde des données de la liste uo sur mysql  
// Temps de sauvegarde : 30 secondes
public function SauvegardeListeUo($travail, $con)  {
	$sql = "insert into listeuo (nomProjet, Tache, Uo, userEmail, annee, mois) values (";
	$sql .= "'" . str_replace("'", "\'", utf8_encode($travail['nom'])) . "', ";
	$sql .= "'" . str_replace("'", "\'", utf8_encode($travail['sujet'])) . "', ";
	$sql .= "" . $travail['heure'] . ", ";
	$sql .= "'" . $travail['mail'] . "', ";
	$sql .= "" . $travail['tyear'] . ", ";
	$sql .= "" . $travail['tmonth'] . ") ";
	
	
	$con->executeUpdate($sql);
}
  
  
  // 1ere fonction de sauvegarde des données de la liste uo sur mysql
  // Inconvenient : temps de sauvegarde trop long : > 7 minutes
public function saveListeuo($projet, $userStory, $uo, $mail, $annee, $month) {
    $liste = new Listeuo();	
  	$liste->setNomprojet($projet);
  	$liste->setTache($userStory);
  	$liste->setUo($uo);
  	$liste->setUseremail($mail);
  	$liste->setAnnee($annee);
  	$liste->setMois($month);
  	$liste->save();
  }
  
// Fin modification **
  /**
   * Recupere les logs d'erreur
   * 
   */
  public function executeGetLog() {
  	// Voir http://www.kenthouse.com/blog/2009/07/fun-with-flexigrids/
  	$page = $this->getRequestParameter('page', '1');
  	$sortname = $this->getRequestParameter('sortname', 'date_erreur');
  	$sortorder = $this->getRequestParameter('sortorder', 'desc');
  	$rp = $this->getRequestParameter('rp', '15');
  	$qtype = $this->getRequestParameter('qtype', '');
  	$query = $this->getRequestParameter('query', '');
  	
  	$c = new Criteria();
  	$count = ImportRedmineLogPeer::doCount($c); // Nb total de logs avant criteres 	
  	if($sortorder == 'desc') {
  		$c->addDescendingOrderByColumn($sortname);
  	}
  	else {
  		$c->addAscendingOrderByColumn($sortname);
  	}
  	if($qtype != '' && $query != '') {
  		$c->add($qtype, $query);
  	}
  	$c->setOffset(($page-1)*$rp);
  	$c->setLimit($rp);
  	$logs = ImportRedmineLogPeer::doSelect($c);
    
	// Conversion en XML, format Flexigrid	
  	$dom = new DomDocument();
  	$rowsNode = $dom->createElement("rows");
  	$pageNode = $dom->createElement("page", $page);
  	$totalNode = $dom->createElement("total", $count);
  	$dom->appendChild($rowsNode);
  	$rowsNode->appendChild($pageNode);
  	$rowsNode->appendChild($totalNode);
  	foreach($logs as $log) {
  		$rowNode = $dom->createElement("row");
  		$rowNode->setAttribute('id', $log->getId());
	  	$rowsNode->appendChild($rowNode);
	  	
	  	$cellNode = $dom->createElement("cell", $log->getDateErreur('d-m-Y H:i:s'));
	  	$rowNode->appendChild($cellNode);  	
	  	$cellNode = $dom->createElement("cell", $log->getEmail());
	  	$rowNode->appendChild($cellNode);
	  	$cellNode = $dom->createElement("cell", $log->getCodeSaphir());
	  	$rowNode->appendChild($cellNode);
	  	$cellNode = $dom->createElement("cell", $log->getErreur());
	  	$rowNode->appendChild($cellNode);
  	}
  	
  	echo $dom->saveXML();
  	return sfView::NONE;
  }
  
  
  
  /**
   * Log les erreurs
   * 
   * @param $error_message
   * @param $email
   * @param $code_saphir
   */
  public function log($error_message, $email = NULL, $code_saphir = NULL) {
  	$log = new ImportRedmineLog();
  	$log->setDateErreur(time());
  	$log->setEmail($email);
  	$log->setCodeSaphir($code_saphir);
  	$log->setErreur($error_message);
  	$log->save();
  }
  
  
  /**
   * 
   * Supprime tous les logs
   */
  public function executeEffacerLog() {
  	$c = new Criteria();
  	$c->add(ImportRedmineLogPeer::ID, 0, Criteria::GREATER_EQUAL);
	ImportRedmineLogPeer::doDelete($c);
  	$this->redirect('importredmine/index');
  }
  
  
  /**
   * Vide le planning (ne sont concernes que les imports Redmine)
   * 
   * @param $utilisateur_id
   * @param $debut
   * @param $fin
   */
  private function _nettoyagePlanning($utilisateur_id, $debut, $fin) {
  	$c = new Criteria();
  	$c->add(ActiviteRealPeer::UTILISATEUR_ID, $utilisateur_id);
  	$c->addAnd(ActiviteRealPeer::COMMENTAIRE, '[Import Redmine]%', Criteria::LIKE);
  	$c->addAnd(ActiviteRealPeer::JOUR, $debut, Criteria::GREATER_EQUAL);
  	$c->addAnd(ActiviteRealPeer::JOUR, $fin, Criteria::LESS_EQUAL);
  	$activite_real = ActiviteRealPeer::doDelete($c);
  }
  
  
  /**
   * Calcul et retourne le prochain jour ouvre et un flag de depassement du mois
   * 
   * @param $timestamp
   */
  private function _prochainJourOuvre($timeStamp) {
  	$timeStamp += self::JOUR_TIME;
	
	while($this->_isJourFerie($timeStamp) || $this->_isWeekEnd($timeStamp)) {
		$timeStamp += self::JOUR_TIME;
	}
	
	return date('d', $timeStamp);
  }
  
  
  /**
   * Retourne le timestamp du dernier jour ouvre
   * 
   */
  private function _dernierJourOuvreDuMois() {
  	$timeStamp = mktime(0, 0, 0, date('m'), date('t'), date('Y')); // Dernier jour du mois
	
  	while($this->_isJourNonOuvre($timeStamp)) {
		$timeStamp -= self::JOUR_TIME;
	}
	
	return date('d', $timeStamp);
  }
  
  
  /**
   * 
   * Calcul si le parametre $timeStamp est un jour ouvre ou non
   * @param $timeStamp
   */
  private function _isJourNonOuvre($timeStamp) {
  	return $this->_isJourFerie($timeStamp) || $this->_isWeekEnd($timeStamp);
  }
  
  
  /**
   * Teste si c'est un jour ferie a partir d'un timestamp
   * 
   * @param $timeStamp
   */
  private function _isJourFerie($timeStamp) {
    $jours_feries = array();
    $year = date('Y');
    
  	// Jours feries fixes
  	$jours_feries[] = mktime(0,0,0, 1, 1,$year); // Jour de l'an
  	$jours_feries[] = easter_date() + self::JOUR_TIME; // Lundi de Paques
  	$jours_feries[] = mktime(0,0,0, 5, 1,$year); // Fete du travail
    $jours_feries[] = mktime(0,0,0, 5, 8,$year); // Armistice 39-45
    $jours_feries[] = easter_date() + 39*self::JOUR_TIME; // Ascension
    $jours_feries[] = easter_date() + 50*self::JOUR_TIME; // Lundi de Pentecote
    $jours_feries[] = mktime(0,0,0, 7,14,$year); // Fete nationale
  	$jours_feries[] = mktime(0,0,0, 8,15,$year); // Assomption
  	$jours_feries[] = mktime(0,0,0,11, 1,$year); // Toussaint
    $jours_feries[] = mktime(0,0,0,11,11,$year); // Armistice
  	$jours_feries[] = mktime(0,0,0,12,25,$year); // Noel
	
  	return in_array($timeStamp, $jours_feries);
  }
  
  
  /**
   * Retourne un boolean indiquant si le timestamp donne est un jour de week end
   * 
   * @param $timeStamp
   */
  private function _isWeekEnd($timeStamp) {
  	$day = date('N', $timeStamp);
  	if($day == '6' || $day == '7') {
  		return true;
  	}
  	else {
  		return false;
  	}
  }
  

  /**
   * Cree et sauvegarde l'activite dans la base de donnees
   * 
   * @param $utilisateur_id
   * @param $projet_id
   * @param $jour
   * @param $duree
   */
  public function saveActivite($utilisateur_id, $projet_id, $jour, $duree) {
    $activite = new ActiviteReal();
  	$activite->setUtilisateurId($utilisateur_id);
  	$activite->setProjetId($projet_id);
  	$activite->setJour($jour);
  	$activite->setOrdre(0);
  	$activite->setDuree($duree);
  	$activite->setCommentaire('[Import Redmine]');
  	$activite->save();
  }
}
