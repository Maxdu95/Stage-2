<?php
//langage iso pour ce fichier
/**
 * main actions.
 *
 * @package    sf_sandbox
 * @subpackage main
 * @author     Your name here
 * @version    SVN: $Id: actions.class.php 2692 2006-11-15 21:03:55Z fabien $
 */
class mainActions extends sfActions
{
  /**
   * Executes index action
   *
   */
  public function executeIndex()
  {
	$this->page_name = 'Saisie de l\'activit&eacute; r&eacute;alis&eacute;e';
  
	if (!($this->month = $this->getRequestParameter('month')))
		$this->month = date('m');
	
	if (!($this->year = $this->getRequestParameter('year')))
		$this->year = date('Y');
	
	$this->days = $this->_buildCalendar($this->month,$this->year);
	
	// Liste de missions
	$this->missions = $this->_getMissions();
	
	// Liste des utilisateurs (pour profil admin)
	if ($this->getUser()->getAttribute('utilisateur')->getRole() == 2) {
		$c = new Criteria();
		$c->addAscendingOrderByColumn(UtilisateurPeer::NOM);
		$this->users = UtilisateurPeer::doSelect($c);
	}
	
	$this->user_id = $this->_retrieveUserId();
	
	$this->user = UtilisateurPeer::retrieveByPk($this->user_id);
	
	//var_dump($this->user->previsionnelSaisi());
	
	// Chargement des donnees en base
	$c = new Criteria();
	$c->addAscendingOrderByColumn(ActiviteRealPeer::JOUR);
	$c->addDescendingOrderByColumn(ActiviteRealPeer::DUREE);
	$c->addAscendingOrderByColumn(ActiviteRealPeer::PROJET_ID);
	$c->add(ActiviteRealPeer::JOUR, $this->year."-".$this->month."-01", Criteria::GREATER_EQUAL);
	if ($this->month == 12) {
		$nextmonth = ($this->year+1)."-01-01";
	} else {
		$nextmonth = ($this->year)."-".($this->month+1)."-01";
	}
	$c->addAnd(ActiviteRealPeer::JOUR, $nextmonth, Criteria::LESS_THAN);
	$c->add(ActiviteRealPeer::UTILISATEUR_ID, $this->user_id);
	$activities = ActiviteRealPeer::doSelect($c);

	// Liste des jours ouvr�s
	$open = mainActions::_getOpenDays($this->month,$this->year);
	
	$this->open_days = $open;
	$this->total_days = count($open);
	
	// R�cup�re toutes les infos jour par jour
	$tab = array_fill(0, count($open), array());
	foreach ($activities as $act) {
		$data = array();
		$data[0] = $act->getProjetId();
		$data[1] = $act->getDuree();
		$data[2] = $act->getProjet()->getNom();
		$data[3] = $act->getCommentaire();
		if ( ($key = array_search($act->getJour('j'), $open)) !== FALSE) {
			$tab[$key][] = $data;
		}
	}
	
	if ($this->hasFlash('prev_popup')) {
		$this->popup = true;
	}
	
	$userID = $this->user_id;
	
	$c = new Criteria();
	$c->add(UtilisateurPeer::ID, $userID);
	$activities = UtilisateurPeer::doSelect($c);
	
	
	//$email = $activities->getEmail();

	
	
	

	

	
	// MAXIME : Recuperation des donnes de Redmine
	
	// Connexion a la base
/*	$redmine = new RedmineUOWrapperMysql();
	

	//$id = $redmine->getId();
	// Recuperation des donnees*/
	//$reponse = $redmine->getProjets("SAPHIR");
/*	$this->Mail= $email;
	
	$this->temps=$redmine->getProjets(57,$this->year,$this->month);
	
	$this->userStory=$redmine->tempsProjets(57,$this->year,$this->month);
	
	$this->uniteOeuvres = null;
	// Fermeture de la connexion
	$redmine->close_db();*/
	
	
//	$this->testMail = $email;

	
	
  }
  


  // Retourne une case du calendrier
  public function executeDay()
  {
	// D�coupage du jour
	if (preg_match("/([0-9]{4})([0-9]{2})([0-9]{2})/", $this->getRequestParameter('day'), $matches))
	{
		$this->year = $matches[1];
		$this->month = $matches[2];
		$this->day = $matches[3];
	} else die;
	
	$this->user_id = $this->_retrieveUserId();
	$this->user = UtilisateurPeer::retrieveByPk($this->user_id);

	// Le mois est-il fix� ?
	$c = new Criteria();
	$c->add(FigerPeer::MOIS, $this->year."-".$this->month."-01");
	$fix = (FigerPeer::doCount($c)>0?true:false);
	
	// Lecture en base
	$c = new Criteria();
	//$c->addAscendingOrderByColumn(ActiviteRealPeer::JOUR);
	//$c->addDescendingOrderByColumn(ActiviteRealPeer::DUREE);
	//$c->addAscendingOrderByColumn(ActiviteRealPeer::PROJET_ID);
	$c->addAscendingOrderByColumn(ActiviteRealPeer::ORDRE);
	
	$c->add(ActiviteRealPeer::JOUR, $this->year."-".$this->month."-".$this->day);
	$c->add(ActiviteRealPeer::UTILISATEUR_ID, $this->user_id);
	
	// Les collaborateurs de la Cellule Agile non admin ne peuvent pas modifier leur planning
	$criteria = new Criteria();
	$criteria->add(UtilisateurPeer::ROLE, 2, Criteria::NOT_EQUAL); // Non admin
	$criteria->addSelectColumn(UtilisateurPeer::ID);
	$celluleAgileMembresRS = UtilisateurPeer::doSelectRS($criteria);
	$celluleAgileMembres = array();
	/* A decommenter lorsque l'on fera tourner le cron pour la passerelle
	while($celluleAgileMembresRS->next()) {
		$row = $celluleAgileMembresRS->getRow();
		$celluleAgileMembres[] = $row[0];
	}
	*/
	
	$currentUserId = $this->getUser()->getAttribute('id');
	
	// Gestion de l'action � effectuer
	if ($this->getUser()->hasCredential('admin') || (!$fix && !in_array($currentUserId, $celluleAgileMembres))) {
		switch($this->getRequestParameter('act')) {
		// Ajout
		case 'add':
			$activities = ActiviteRealPeer::doSelect($c);
			$occupation = 0;
			$nb_act = 0;
			foreach($activities as $act) {
				$occupation += $act->getDuree();
				$nb_act++;
			}
		
			$time = $this->getRequestParameter('time');
			$mission = $this->getRequestParameter('mission');
			$comment = $this->getRequestParameter('comment');
			if ($time >= 1 && ($time + $occupation) <= $this->user->getNbheureJour()) {
				$act = new ActiviteReal();
				$act->setUtilisateurId($this->user_id);
				$act->setProjetId($mission);
				$act->setDuree($time);
				$act->setCommentaire($comment);
				$act->setOrdre($nb_act + 1);
				$act->setJour($this->year."-".$this->month."-".$this->day);
				$act->save();
				
				$activities[] = $act;
				$occupation += $time;
				$nb_act++;
			}
			break;
		// Suppression
		case 'del':
			ActiviteRealPeer::doDelete($c);
			$activities = array();
			$occupation = 0;
			break;
		// Copie
		case 'copy':
			$this->getUser()->setAttribute('clipboard', $this->getRequestParameter('day'));
			break;
		case 'paste':
			//$source = $this->getRequestParameter('source');
			$clipboard = $this->getUser()->getAttribute('clipboard');
			if ($clipboard == null) break;
			
			if (preg_match("/([0-9]{4})([0-9]{2})([0-9]{2})/", $clipboard, $matches))
			{
				$syear = $matches[1];
				$smonth = $matches[2];
				$sday = $matches[3];
			} else die;	// Ne rien faire si le format de date n'est pas valide
		
			ActiviteRealPeer::doDelete($c);
			
			// Lecture de l'original
			$c = new Criteria();
			//$c->addAscendingOrderByColumn(ActiviteRealPeer::JOUR);
			//$c->addDescendingOrderByColumn(ActiviteRealPeer::DUREE);
			//$c->addAscendingOrderByColumn(ActiviteRealPeer::PROJET_ID);
			$c->addAscendingOrderByColumn(ActiviteRealPeer::ORDRE);
			
			$c->add(ActiviteRealPeer::JOUR, $syear."-".$smonth."-".$sday);
			$c->add(ActiviteRealPeer::UTILISATEUR_ID, $this->user_id);
			$orig = ActiviteRealPeer::doSelect($c);
			
			// Copie
			$activities = array();
			$occupation = 0;
			foreach($orig as $old) {
				$new = $old->copy();
				$new->setJour($this->year."-".$this->month."-".$this->day);
				$new->save();
				$activities[] = $new;
				$occupation += $new->getDuree();
			}
			
			break;
		// Ajout via formulaire d�taill�
		case 'addfull':
			ActiviteRealPeer::doDelete($c);
			$activities = array();
			$occupation = 0;
			$nb_act = 0;
			$missions = $this->getRequestParameter('missions');
			$times = $this->getRequestParameter('times');
			$comments = $this->getRequestParameter('comments');
			foreach ($missions as $idx => $mission)
			{
				$act = new ActiviteReal();
				$act->setUtilisateurId($this->user_id);
				$act->setProjetId($mission);
				$act->setOrdre($nb_act+1);
				$act->setDuree($times[$idx]);
				$act->setCommentaire($comments[$idx]);
				$act->setJour($this->year."-".$this->month."-".$this->day);
				$act->save();
				$activities[] = $act;
				$occupation += $times[$idx];
				$nb_act++;
			}
			break;
		default:
			break;
		}
		$this->activities = $activities;
		$this->occupation = $occupation;
	} else {
		$this->activities = null;
		$this->occupation = null;
	}
	
	// Renvoie la r�ponse au format XML
	$this->getResponse()->setHttpHeader("Content-Type", "application/xml");
  }
  
  // Retourne le formulaire de journ�e d�taill�e
  public function executeDetails()
  {	
	if (preg_match("/([0-9]{4})([0-9]{2})([0-9]{2})/", $this->getRequestParameter('day'), $matches))
	{
		$this->year = $matches[1];
		$this->month = $matches[2];
		$this->day = $matches[3];
	} else die;
	
	$this->user_id = $this->_retrieveUserId();
	
	$this->user = UtilisateurPeer::retrieveByPk($this->user_id);

	$c = new Criteria();
	//$c->addAscendingOrderByColumn(ActiviteRealPeer::JOUR);
	//$c->addDescendingOrderByColumn(ActiviteRealPeer::DUREE);
	//$c->addAscendingOrderByColumn(ActiviteRealPeer::PROJET_ID);
	$c->addAscendingOrderByColumn(ActiviteRealPeer::ORDRE);
	
	$c->add(ActiviteRealPeer::JOUR, $this->year."-".$this->month."-".$this->day);
	$c->add(ActiviteRealPeer::UTILISATEUR_ID, $this->user_id);
	$this->activities = ActiviteRealPeer::doSelect($c);

	$missions = $this->_getMissions();
	
	foreach ($this->activities as $act) {
		$missions[$act->getProjet()->getId()] = $act->getProjet()->getNom()  . " (" . $act->getProjet()->getOtp()->getOtp() . ")" ;
	}
	
	// Ajoute une activit� vide si rien de rempli
	if (empty($this->activities)) {
		$activ = new ActiviteReal();
		list($key,$val) = each($missions);
		// Cas sans liste de projets par d�faut => on a un tableau de tableaux
		if (is_array($val)) {
			list($key,$val) = each($val);
		}
		$activ->setProjetId($key);
		$this->activities = array($activ);
	}
	
	$this->missions = $missions;
  }
  
  // Export Excel
  public function executeExcel()
  {
	$year = $this->getRequestParameter('year');
	$month = $this->getRequestParameter('month');
	
	$this->user_id = $this->_retrieveUserId();
	$user = UtilisateurPeer::retrieveByPk($this->user_id);
	
	
	

	

  
  	$excel = new sfExcel();
	
	// D�finition des styles
	$title_style =& $excel->style();
	$title_style->setItalic();
	$title_style->setColor('blue');
	
	$top_style =& $excel->style();
	$top_style->setBold();
	$top_style->setAlign('center');
	$top_style->setColor('red');
	
	$line_style =& $excel->style();
	$line_style->setFgColor('yellow');
	
	$new_style =& $excel->style();
	$new_style->setAlign('right');
	$new_style->setAlign('vcenter');
	
	$just_style =& $excel->style();
	$just_style->setAlign('left');
	$just_style->setTextWrap();
	$just_style->setAlign('top');
	
	
	$style_intro =& $excel->style();
	$style_intro->setAlign('left');
	$style_intro->setColor('purple');


	$grey_style =& $excel->style();
	$grey_style->setFgColor('grey');
	
	$empty_style =& $excel->style();
	$empty_style->setFgColor('red');
	
	$bold_style =& $excel->style();
	$bold_style->setBold();
	
	$right_align =& $excel->style();
	$right_align->setAlign('right');
	
	// Style des cases pour signatures
	$border_lt =& $excel->style();
	$border_lt->setLeft(1);
	$border_lt->setTop(1);
	
	$border_tr =& $excel->style();
	$border_tr->setTop(1);
	$border_tr->setRight(1);
	
	$border_lb =& $excel->style();
	$border_lb->setLeft(1);
	$border_lb->setBottom(1);
	
	$border_br =& $excel->style();
	$border_br->setBottom(1);
	$border_br->setRight(1);
	
	$border_l =& $excel->style();
	$border_l->setLeft(1);
	
	$border_r =& $excel->style();
	$border_r->setRight(1);
	
	$border_t =& $excel->style();
	$border_t->setTop(1);
	
	$border_b =& $excel->style();
	$border_b->setBottom(1);
	
	$border_lr =& $excel->style();
	$border_lr->setLeft(1);
	$border_lr->setRight(1);
	
	$border_ltr =& $excel->style();
	$border_ltr->setLeft(1);
	$border_ltr->setTop(1);
	$border_ltr->setRight(1);
	
	$border_lbr =& $excel->style();
	$border_lbr->setLeft(1);
	$border_lbr->setBottom(1);
	$border_lbr->setRight(1);

	$df = new sfDateFormat('fr');
	
	$excel->newSheet("Activite_".utf8_decode($user->getNom()."_".$user->getPrenom()));
	
	// Mise en paysage
	$excel->setLandscape();
	
	$excel->setColumn(24);
	$content = $user->getNom()." ".$user->getPrenom();
	$content .= utf8_encode(" : Activite ");
	$content .= '(' . ucwords($df->format(mktime(0,0,0,$month,1,$year), 'MMMM yyyy')) . ')';
	
	$excel->cell($content, 'String', $title_style);
	
	// En t�tes
	$excel->row();
	$excel->setColumn(24);
	$excel->cell("Date", 'String', $top_style);
	$excel->setColumn(14);
	if ($user->getNbheureJour() == '4')
		$excel->cell("Temps (jours)", 'String', $top_style);
	else
		$excel->cell("Temps (heures)", 'String', $top_style);
		
	$excel->setColumn(36);
	$excel->cell(utf8_encode("Activité"), 'String', $top_style);
	$excel->setColumn(28);
	$excel->cell("Commentaire", 'String', $top_style);
	
	$open    = mainActions::_getAllOpenDays($month,$year);
	$feries  = mainActions::_getJoursFeries($year);
	
	$pair = false;

	$begin = true;
	$lundi = false;

	$jours_travailles = 0;	// Compte le total des jours travaill�s
	
	foreach ($open as $day)
	{
		// Ligne grise entre les semaines
		if ( $begin == false && date('w', strtotime($day)) == 1 && $lundi == false )
		{
			$excel->row();
			$excel->cell('','String',$grey_style);
			$excel->cell('','String',$grey_style);
			$excel->cell('','String',$grey_style);
			$excel->cell('','String',$grey_style);
		}
		$begin = false;

		if ($pair) {
			$style = $line_style;
			$pair = false;
		} else {
			$style = null;
			$pair = true;
		}
		
		// Si jour f�ri�
		if (in_array($day, $feries)) {
			$excel->row();
			$excel->cell(ucwords($df->format(strtotime($day), 'EEEE dd')), 'String', $style);
			
			$excel->cell(1, 'Number', $style);
				
			$excel->cell(utf8_encode("Jour f�ri�"), 'String', $style);
			$excel->cell("Pas de commentaire", 'String', $style);
			continue;
		}
		
		$c = new Criteria();
		//$c->addAscendingOrderByColumn(ActiviteRealPeer::JOUR);
		//$c->addDescendingOrderByColumn(ActiviteRealPeer::DUREE);
		//$c->addAscendingOrderByColumn(ActiviteRealPeer::PROJET_ID);
		$c->addAscendingOrderByColumn(ActiviteRealPeer::ORDRE);
			
		$c->add(ActiviteRealPeer::JOUR, $day);
		$c->add(ActiviteRealPeer::UTILISATEUR_ID, $this->user_id);
		$activities = ActiviteRealPeer::doSelect($c);
		
		foreach ($activities as $act)
		{
			if (!$act->isAbsence()) {
				$jours_travailles += $act->getDuree();
			}
		
			$excel->row();
			$excel->cell(ucwords($df->format(strtotime($day), 'EEEE dd')), 'String', $style);
			
			if ($user->getNbheureJour() <> 4)
				$excel->cell($act->getDuree(), 'Number', $style);
			else
				$excel->cell($act->getDuree()/4, 'Number', $style);
			
			if ($act->getProjet()->getOtp()->getOtp() <> '')
				$excel->cell($act->getProjet()->getOtp()->getOtp() . " - " . $act->getProjet()->getNom(), 'String', $style);
			else
				$excel->cell($act->getProjet()->getNom(), 'String', $style);
				
			$excel->cell($act->getCommentaire(), 'String', $style);
		}
		
		// Jour non rempli
		if (count($activities) == 0) {
			$excel->row();
			$excel->cell(ucwords($df->format(strtotime($day), 'EEEE dd')), 'String', $empty_style);
			
			$excel->cell(1, 'Number', $empty_style);
				
			$excel->cell("Non définie", 'String', $empty_style);
			$excel->cell("Pas de commentaire", 'String', $empty_style);
		}
	}
	
	if (!$user->getProfil()->getAgent()) {
		// Case total
		$excel->row();
		$excel->row();
		$excel->cell("Total des jours travaillé", "String", $bold_style, true);
		$excel->cell( ($jours_travailles / $user->getNbheureJour()) . " / " . count($open) , "String", $right_align);
	
		// Cadres signatures
		$excel->row();
		$excel->row();
		$excel->cell("Date / Signature");
		$excel->cell("");
		$excel->cell("");
		$excel->cell("Signature Hiérarchique");
		
		$excel->row();
		$excel->cell("", "String", $border_lt);
		$excel->cell("", "String", $border_tr);
		$excel->cell("");
		$excel->cell("", "String", $border_ltr);
		$excel->row();
		$excel->cell("", "String", $border_l);
		$excel->cell("", "String", $border_r);
		$excel->cell("");
		$excel->cell("", "String", $border_lr);
		$excel->row();
		$excel->cell("", "String", $border_lb);
		$excel->cell("", "String", $border_br);
		$excel->cell("");
		$excel->cell("", "String", $border_lbr);
	}
	
	//////////////////////////////////////////////////////////////////
	// Pour les agents : Export MAGELLAN
	//////////////////////////////////////////////////////////////////
	
	if ($user->getProfil()->getAgent()) {
		$excel->newSheet("Export Magellan");
		
		// Mise en paysage
		$excel->setLandscape();
		
		$excel->setColumn(14);
		$content = $user->getNom()." ".$user->getPrenom();
		$content .= utf8_encode(" : Activité ");
		$content .= '(' . ucwords($df->format(mktime(0,0,0,$month,1,$year), 'MMMM yyyy')) . ')';
		

		
		$excel->cell($content, 'String', $title_style);
		
		$excel->row();
		$excel->setColumn(14);
		$excel->cell("Date", 'String', $top_style);
		$excel->setColumn(14);
		if ($user->getNbheureJour() == '4')
			$excel->cell("Temps (jours)", 'String', $top_style);
		else
			$excel->cell("Temps (heures)", 'String', $top_style);
		$excel->setColumn(36);
		$excel->cell(utf8_encode("Activité"), 'String', $top_style);
		$excel->setColumn(28);
		$excel->cell("Commentaire", 'String', $top_style);
		
		$open   = mainActions::_getAllOpenDays($month,$year);
		$feries = mainActions::_getJoursFeries($year);
		$pair = false;

		$begin = true;
		$lundi = false;

		foreach ($open as $day)
		{
			// Ligne grise entre les semaines
			if ( $begin == false && date('w', strtotime($day)) == 1 && $lundi == false )
			{
				$excel->row();
				$excel->cell('','String',$grey_style);
				$excel->cell('','String',$grey_style);
				$excel->cell('','String',$grey_style);
				$excel->cell('','String',$grey_style);
			}
			$begin = false;
			
			// Si jour f�ri�
			if (in_array($day, $feries)) {
				$excel->row();
				$excel->cell(ucwords($df->format(strtotime($day), 'EEEE dd')), 'String', $line_style);
				
				$excel->cell(1, 'Number', $line_style);
					
				$excel->cell(utf8_encode("Jour fêrié"), 'String', $line_style);
				$excel->cell("Pas de commentaire", 'String', $line_style);
				continue;
			}
			
			$c = new Criteria();
			$c->addAscendingOrderByColumn(ActiviteRealPeer::JOUR);
			$c->addDescendingOrderByColumn(ActiviteRealPeer::DUREE);
			$c->addAscendingOrderByColumn(ActiviteRealPeer::PROJET_ID);
				
			$c->add(ActiviteRealPeer::JOUR, $day);
			$c->add(ActiviteRealPeer::UTILISATEUR_ID, $this->user_id);
			$activities = ActiviteRealPeer::doSelect($c);
			
			foreach ($activities as $act)
			{
				// Ligne d'absence
				if ( strncmp($act->getProjet()->getNom(),"Absences",8) == 0 ) {
					$excel->row();
					$excel->cell(ucwords($df->format(strtotime($day), 'EEEE dd')), 'String', $line_style);
					
					if ($user->getNbheureJour() <> 4)
						$excel->cell($act->getDuree(), 'Number', $line_style);
					else
						$excel->cell($act->getDuree()/4, 'Number', $line_style);
						
					$excel->cell("Absence", 'String', $line_style);
					$excel->cell($act->getCommentaire(), 'String', $line_style);
				} else {
					$excel->row();
					$excel->cell(ucwords($df->format(strtotime($day), 'EEEE dd')), 'String');
					
					if ($user->getNbheureJour() <> 4)
						$excel->cell($act->getDuree(), 'Number');
					else
						$excel->cell($act->getDuree()/4, 'Number');
						
					$excel->cell($act->getProjet()->getOtp()->getOtp(), 'String');
					$excel->cell("", 'String');
				}
			}
			
			// Jour non rempli
			if (count($activities) == 0) {
				$excel->row();
				$excel->cell(ucwords($df->format(strtotime($day), 'EEEE dd')), 'String', $empty_style);
				
				$excel->cell(1, 'Number', $empty_style);
					
				$excel->cell("Non définie", 'String', $empty_style);
				$excel->cell("Pas de commentaire", 'String', $empty_style);
			}
		}
		
		// Cadres signatures
		$excel->row();
		$excel->row();
		$excel->cell("Date / Signature");
		$excel->cell("");
		$excel->cell("");
		$excel->cell(utf8_encode("Signature Hi�rarchique"));
		
		$excel->row();
		$excel->cell("", "String", $border_lt);
		$excel->cell("", "String", $border_tr);
		$excel->cell("");
		$excel->cell("");
		$excel->cell("", "String", $border_ltr);
		$excel->row();
		$excel->cell("", "String", $border_l);
		$excel->cell("", "String", $border_r);
		$excel->cell("");
		$excel->cell("", "String", $border_lr);
		$excel->row();
		$excel->cell("", "String", $border_lb);
		$excel->cell("", "String", $border_br);
		$excel->cell("");
		$excel->cell("", "String", $border_lbr);
	}

// Début modification 	**
		// Tableau excel des US de Redmine
	
		$excel->newSheet("Projet_".$user->getNom()."_".$user->getPrenom());
		
		// Mise en paysage
		$excel->setLandscape();
		
		$content2 = $user->getNom()." ".$user->getPrenom();
		$content2 .= utf8_encode(" : Projets ");
		$content2 .= '(' . ucwords($df->format(mktime(0,0,0,$month,1,$year), 'MMMM yyyy')) . ')';
		
		$excel->setColumn(15);
		$excel->cell($content2, 'String', $title_style);
		$excel->row();
		
		//$excel->cell(utf8_encode("Liste des US et des UO associés aux projets :"),'String', $style_intro);
		$excel->cell("Liste des US et des UO associés aux projets :",'String', $style_intro);
		$excel->row();
		$excel->setColumn(25);
		$excel->cell("Projet", 'String', $top_style);
		$excel->setColumn(90);
		$excel->cell("User Story", 'String', $top_style);
		$excel->setColumn(20);
		$excel->cell("Uo", 'String', $top_style); 
		//$excel->cell("email", 'String', $top_style);
		
		//recupere l'email du l'utilisateur selectionné de la table utilisateur
		$email = $user->getEmail();
		
		// selectionne les projets de l'utilisateur selectionné grace à l'email de l'utilisateur ainsi que de l'année et du mois séléctionné
		$b = new Criteria();
		$b->addJoin(ListeuoPeer::USEREMAIL, UtilisateurPeer::EMAIL);
		$b->add(ListeuoPeer::ANNEE, $year);
		$b->add(ListeuoPeer::MOIS, $month);
		$b->add(UtilisateurPeer::EMAIL, $email);
	//	$b->add(ListeuoPeer::USEREMAIL, $email);
		$b->add(UtilisateurPeer::MASQUE, 0);
		$liste = ListeuoPeer::doSelect($b);

		$i = 0;
		
		//correspond au nombre d'heure de travail dans une journée
		//remplace $user->getNbheureJour()*2 qui ne donnait pas le résultat attendu. 
	$e = 7;
	//	$e = ($user->getNbheureJour())*2;
		
		// on obtient une ligne pour chaque projet récuperé avec leur nom, leur user story, leur uo et l'email de l'utilisateur ayant travaillé sur le projet			
		
	foreach ($liste as $projet)
		{
			$excel->row();
			$excel->cell($projet->getNomprojet(), 'String', $just_style);			
			$excel->cell($projet->getTache(), 'String', $just_style);
			if ($user->getProfil()->getAgent())
			{
				$excel->cell($projet->getUO(), 'Number', $new_style);	
				if($projet->getNomprojet() <> "Absence-congés"){
					$i = $i + $projet->getUO();	
				}		
			}
			else{
				$excel->cell(round(($projet->getUO() / $e),2), 'Number', $new_style);
				if($projet->getNomprojet() <> "Absence-congés"){
					$i = $i + ($projet->getUO() / $e);
				}
			}
		//	$excel->cell($projet->getUseremail(), 'String', $just_style);
		}
		$excel->row();
		$excel->row();
		$excel->cell("");
		$excel->cell( "Total : ", "String", $new_style);
		if ($user->getProfil()->getAgent()){
			$excel->cell( $i, "Number", $new_style);

		}
		else {
		//si la somme des uo de listeuo du mois est superieur au nombre de jour travaillé durant
		// le mois, $i prend la valeur de ($jours_travailles / $user->getNbheureJour())
		if ($i > ($jours_travailles / $user->getNbheureJour())){
		$excel->cell( ($jours_travailles / $user->getNbheureJour()), "Number", $new_style);
		}
		else{
		$excel->cell( $i, "Number", $new_style);
		}
		}
		$excel->row();
		$excel->row();
		$excel->cell("Date / Signature");
		$excel->cell("");
		$excel->cell("Signature Hiérarchique");
		
		$excel->row();
		$excel->cell("", "String", $border_ltr);
		$excel->cell("");
		$excel->cell("", "String", $border_ltr);
		$excel->row();
		$excel->cell("", "String", $border_lr);
		$excel->cell("");
		$excel->cell("", "String", $border_lr);
		$excel->row();
		$excel->cell("", "String", $border_lbr);
		$excel->cell("");
		$excel->cell("", "String", $border_lbr);
		
	/*	$excel->cell(utf8_encode("Liste des UO associ�s par projet :"),'String', $style_intro);
		$excel->row();		
		$excel->setColumn(25);
		$excel->cell("Projet", 'String', $top_style);
		$excel->setColumn(14);			
		$excel->cell("Uo", 'String', $top_style);		

		
  foreach ($temps as $projet)
		{			
			$excel->row();
			$excel->cell($projet['nom'],'String', $new_style);
			$excel->cell($projet['heure'],'String', $new_style);
			
			
		}*/
		
// Fin modification **		
		
			
		
		

		

	
	$excel->outputWorkbook("Activite.xls");

    return sfView::NONE;
  }
  
  public function executeTableauActivite()
  {
	$year = $this->getRequestParameter('year');
	
	$this->user_id = $this->_retrieveUserId();
	$user = UtilisateurPeer::retrieveByPk($this->user_id);
  
	//echo "<pre>";
	$tableau = new tableauActivite($this->user_id, $year);
	
	//print_r($tableau); die;
  
	$df = new sfDateFormat('fr');
  
  	$excel = new sfExcel();
	
	// D�finition des styles
	$month_style =& $excel->style();
	$month_style->setBold();
	$month_style->setAlign('left');
	$month_style->setTop(2);
	$month_style->setBottom(2);
	$month_style->setLeft(2);
	$month_style->setRight(2);
	
	$top_style =& $excel->style();
	$top_style->setBold();
	$top_style->setItalic();
	$top_style->setAlign('center');
	$top_style->setTop(2);
	$top_style->setBottom(2);
	$top_style->setLeft(2);
	$top_style->setRight(2);
	
	$m_style =& $excel->createStyle(array(
		'bold' => true,
		'italic' => true,
		'align' => 'center',
		'top' => 2,
		'bottom' => 2,
		'left' => 2,
		'right' => 1));
	$a_style =& $excel->createStyle(array(
		'bold' => true,
		'italic' => true,
		'align' => 'center',
		'top' => 2,
		'bottom' => 2,
		'left' => 1,
		'right' => 2));
	
	$excel->newSheet("Tableau annuel");
	
	// Mise en paysage
	$excel->setLandscape();
	
	// Entete noms des mois
	$excel->setColumn(8);
	$excel->cell("", "String", $top_style);
	
	for ($i=1; $i<=12; $i++) {
		$excel->setColumn(8);
		//echo strtoupper($df->format(mktime(0,0,0,$i,1,date('Y')), 'MMMM', null, $charset));
		$excel->cell(ucwords($df->format(mktime(0,0,0,$i,1,date('Y')), 'MMMM')), "String", $month_style, true, 1);
	}
	
	$excel->setColumn(8);
	$excel->cell("", "String", $top_style);
	
	// Entete m / a
	$excel->row();
	
	$excel->setColumn(8);
	$excel->cell("", "String", $top_style);
	
	for ($i=1; $i<=12; $i++) {
		$excel->setColumn(8);
		$excel->cell("m", "String", $m_style);
		$excel->setColumn(8);
		$excel->cell("a", "String", $a_style);
	}
	
	$excel->setColumn(8);
	$excel->cell("", "String", $top_style);
	
	// 31 lignes de contenu
	for ($i=1; $i<=31; $i++) {
		$excel->row();
		$excel->cell($i, "String", $top_style);
		for ($j=1; $j<=12; $j++) {
			$excel->cell($tableau->getJour($i,$j,'m'));
			$excel->cell($tableau->getJour($i,$j,'a'));
		}
		$excel->cell($i, "String", $top_style);
	}

	$excel->outputWorkbook("Activite.xls");

    return sfView::NONE;
  }

  private function _retrieveUserId()
  {
	if ($this->getUser()->getAttribute('utilisateur')->getRole() == 2) {
		$user = $this->getRequestParameter('user');
	}
	
	if (!isset($user) || $user == null)
		$user = $this->getUser()->getAttribute('id');
	
	return $user;
  }
  
  // Construit un calendrier du mois
  private function _buildCalendar($month=0, $year=0)
  {
	if ($month == 0 || $year == 0)
		$now = time();
	else
		$now = mktime(0,0,0,$month,1,$year);
  
	$first_of_month = mktime(0,0,0,date('m',$now),1,date('Y',$now));

	//Retrouve le lundi de la premiere semaine
	$first_day_stamp = $first_of_month;
	while (date('w',$first_day_stamp) > 1)
	{
		$first_day_stamp = strtotime('-1 day',$first_day_stamp);
	}

	// Ins�re les jours du mois pr�c�dent
	$curr_stamp = $first_day_stamp;
	while ($curr_stamp < $first_of_month)
	{
		$days[1][] = $curr_stamp;
		$curr_stamp = strtotime('+1 day',$curr_stamp);
	}

	// Ins�re les jours du mois courant
	$week = 1;
	while (date('n',$curr_stamp) == date('n',$first_of_month))
	{
		$days[$week][] = $curr_stamp;
		$week = (date('w',$curr_stamp)) == 0 ? $week + 1 : $week;
		$curr_stamp = strtotime('+1 day',$curr_stamp);
	}

	// Ins�re les jours du mois suivant
	while (date('w',$curr_stamp) > 1)
	{
		$days[$week][] = $curr_stamp;
		$curr_stamp = strtotime('+1 day',$curr_stamp);
	}
	
	return $days;
  }

  // R�cup�re la liste de missions (compl�te ou s�lectionn�es seulement)
  private function _getMissions($all = false)
  {
	$missions = array();
	$c = new Criteria();
	$c->add(LienUtilisateurProjetPeer::UTILISATEUR_ID, $this->getUser()->getAttribute('id'));
	$c->addAscendingOrderByColumn(ProjetPeer::NOM);
	if (LienUtilisateurProjetPeer::doCountJoinProjet($c) > 0 && !$all) {
		// L'utilisateur a choisi des missions pr�cises, ne retourner que celles l�
		$c->add(ProjetPeer::MASQUE, 0);
		$projets = LienUtilisateurProjetPeer::doSelectJoinProjet($c);
		foreach ($projets as $pj) {
			$missions[$pj->getProjet()->getId()] = $pj->getProjet()->getNom() . " (" . $pj->getProjet()->getOtp()->getOtp() . ")" ;
		}
	} else {
		// Retourne toutes les missions existantes
		/*$c = new Criteria();
		$c->addAscendingOrderByColumn(ProjetPeer::NOM);
		$misslist = ProjetPeer::doSelect($c);
		foreach ($misslist as $mission) {
			$missions[$mission->getId()] = $mission->getLibelle();
		}*/
		
		$c = new Criteria();
		$c->addAscendingOrderByColumn(ThemePeer::NOM);
		
		$c2 = new Criteria();
		$c2->addAscendingOrderByColumn(ProjetPeer::LIBELLE);
		
		$themes = ThemePeer::doSelect($c);
		foreach($themes as $theme) {
			$projets = array();
			foreach ($theme->getProjets($c2) as $pj) {
				$projets[$pj->getId()] = $pj->getNom() . " (" . $pj->getOtp()->getOtp() . ")" ;
			}
			$missions[$theme->getNom()] = $projets;
		}
	}
	return $missions;
  }
  
  // Calcul du jour de Paques
  public static function _getPaques($y)
  {
  	$a = $y % 4;
  	$b = $y % 7;
  	$c = $y % 19;
  	$m = 24;
  	$n = 5;
  	$d = (19*$c + $m) % 30;
  	$e = (2*$a + 4*$b + 6*$d + $n) % 7;
  	$date = 22 + $d + $e;
  	if ($d==29 && $e==6) return mktime(0,0,0,4,11,$y);
  	if ($d==28 && $e==6) return mktime(0,0,0,4,19,$y);
  	if ($date > 31)
  		return mktime(0,0,0,4,$d+$e-8,$y);
    else
  		return mktime(0,0,0,3,22+$d+$e+1,$y);
  }
  
  // Liste les jours travaill�s
  // flag complete : pour les agents : complete la semaine magellan
  public static function _getOpenDays($month=0, $year=0, $complete = false)
  {
    // Jours feries en timestamp
    $jours_feries = mainActions::_getJoursFeries($year, false);

    // r�cup�ration des parametres  	
  	if ($month == 0) $month = date('m');
  	if ($year == 0)  $year = date('Y');
  	
  	// nombre de jours dans le mois
    $last_day = date('d', mktime(0,0,0,$month+1,0,$year));
    
    // construction de la liste des jours ouvr�s
    $ret = array();
    
    // pour chacun des jours du mois
    for ($i=1; $i<=$last_day; $i++)
    {
      // jour de la semaine correspondant
  		$day = date('w', mktime(0,0,0,$month,$i,$year));
      
      // si le jour n'est pas un WE, et n'est pas f�ri�, on le garde
  		if ($day!=0 && $day!=6 && !in_array(mktime(0,0,0,$month,$i,$year), $jours_feries) )
  			$ret[] = sprintf('%04d-%02d-%02d', $year, $month, $i);
  	}
  	
    if ($complete)
    {
    	// Si premier jour <= mercredi, ajouter les jours pr�c�dents pour semaine Magellan
    	/*$ld = date('w', mktime(0,0,0,$month,1,$year));
    	if ($ld <= 3 && $ld <> 0) {
    		for ($i = $ld-1; $i>0; $i--) {
    			if (!in_array(mktime(0,0,0,$month-1,-$i+1,$year), $jours_feries))
    				$ret[] = sprintf('%04d-%02d-%02d', $year, $month-1, date('d',mktime(0,0,0,$month,-$i+1,$year)));
    		}
    	}*/
    	
    	// Si dernier jour >= mercredi, ajouter les jours suivants pour semaine Magellan
    	$ld = date('w', mktime(0,0,0,$month+1,0,$year));
    	if ($ld >= 3 && $ld <> 6)
      {
    		for ($i = $ld; $i<5; $i++)
        {
    			if (!in_array(mktime(0,0,0,$month+1,$i-$ld+1,$year), $jours_feries))
    				$ret[] = sprintf('%04d-%02d-%02d', $year, $month+1, $i - $ld + 1);
    		}
    	}
    }
  	
  	return $ret;
  }
  
  // Liste les jours f�ri�s
  public static function _getJoursFeries($year = 0, $toDate = true)
  {
    if ($year == 0)  $year = date('Y');
    
    // construction de la liste des jours f�ri�es
    $jours_feries = array();
    
  	// Jours feries fixes
  	$jours_feries[] = mktime(0,0,0, 1, 1,$year); // Jour de l'an
  	$jours_feries[] = mktime(0,0,0, 5, 1,$year); // Fete du travail
    $jours_feries[] = mktime(0,0,0, 5, 8,$year); // Armistice 39-45
    $jours_feries[] = mktime(0,0,0, 7,14,$year); // Fete nationale
  	$jours_feries[] = mktime(0,0,0, 8,15,$year); // Assomption
  	$jours_feries[] = mktime(0,0,0,11, 1,$year); // Toussaint
    $jours_feries[] = mktime(0,0,0,11,11,$year); // Armistice
  	$jours_feries[] = mktime(0,0,0,12,25,$year); // Noel
    
  	// Jours feries d�pendants de Paques
  	$jours_feries['paques'] = mainActions::_getPaques($year);
  	$jours_feries[] = strtotime("+38 days", $jours_feries['paques']);
  	$jours_feries[] = strtotime("+49 days", $jours_feries['paques']);
  	
    // convertion des timestamp en date Y-m-d, si demand�
    if ($toDate)
    {
    	foreach ($jours_feries as $key => $time)
    		$jours_feries[$key] = date('Y-m-d', $time);
    }
    
  	return $jours_feries;
  }
  
  // Liste les jours travaill�s + jours f�ri�s
  public static function _getAllOpenDays($month=0, $year=0, $complete = false)
  {
  	if ($month == 0) $month = date('m');
  	if ($year == 0)  $year = date('Y');
  	
    $last_day = date('d', mktime(0,0,0,$month+1,0,$year));
    
  	$ret = array();
  	for ($i=1; $i<=$last_day; $i++)
    {
  		$day = date('w', mktime(0,0,0,$month,$i,$year));
  		if ($day!=0 && $day!=6)
  			$ret[] = sprintf('%04d-%02d-%02d', $year, $month, $i);
  	}
	
  	$jours_feries = mainActions::_getJoursFeries($year);
  	
  	if($complete) {
	  	// Si dernier jour >= mercredi, ajouter les jours suivants pour semaine Magellan
	  	$ld = date('w', mktime(0,0,0,$month+1,0,$year));
	  	if ($ld >= 3 && $ld <> 6)
	    {
	  		for ($i = $ld; $i<5; $i++)
	      {
	  			if (!in_array(mktime(0,0,0,$month+1,$i-$ld+1,$year), $jours_feries))
	  				$ret[] = sprintf('%04d-%02d-%02d', $year, $month+1, $i - $ld + 1);
	  		}
	  	}
  	}
  	
  	return $ret;
  }
}
