<?php

class RedmineWrapperMysql {
	
	private 
		$host,
		$port,
		$user,
		$pass,
		$database,
		$link;
	
	
	/**
	 * 
	 * Connexion au serveur MYSQL de Redmine et selection de la base de donnees
	 * @throws CustomMySQLException
	 */
	public function __construct() {
		$this->host = sfConfig::get('mod_importredmine_host');
		$this->port = sfConfig::get('mod_importredmine_port');
		$this->user = sfConfig::get('mod_importredmine_user');
		$this->pass = sfConfig::get('mod_importredmine_pass');
		$this->database = sfConfig::get('mod_importredmine_database');		
		$this->link = mysql_connect($this->host . ':' . $this->port, $this->user, $this->pass);
	 
		if(!$this->link)
			throw new CustomMySQLException('Connexion impossible a la BDD : ' . mysql_error($this->link));
		 
		$base = mysql_select_db($this->database, $this->link);	 
		if (!$base)
			throw new CustomMySQLException(mysql_error($this->link));
	}
	
	
	/**
	 * 
	 * Recupere les informations de Redmine sous forme de tableau indexe par l'email utilisateur
	 * Les utilisateurs sont ceux qui ont la case Saphir Auto cochee et qui ont un compte Redmine actif
	 * 
	 * @param $begin Date de debut
	 * @param $end Date de fin
	 * @throws CustomMySQLException
	 */
	public function getUserProjectInfos($begin, $end) {
		$project_code_saphir_custom_field_id = sfConfig::get('mod_importredmine_project_code_saphir_custom_field_id');
		$user_code_saphir_custom_field_id = sfConfig::get('mod_importredmine_user_code_saphir_custom_field_id');
		$saphir_auto_custom_field_id = sfConfig::get('mod_importredmine_saphir_auto_custom_field_id');
		
		$begin = mysql_real_escape_string($begin);
		$end = mysql_real_escape_string($end);
		$query = 'SELECT users.mail, 
			projects.name AS nom_projet, projects_codes_saphir.value AS project_code_saphir, 
			ROUND(SUM(time_entries.hours),2) AS heure_totale,
			users_codes_saphir.code AS user_code_saphir
			FROM users
				INNER JOIN time_entries
					ON time_entries.user_id = users.id
				INNER JOIN custom_values AS saphir_autos
					ON users.id = saphir_autos.customized_id
				INNER JOIN custom_values AS projects_codes_saphir
					ON time_entries.project_id = projects_codes_saphir.customized_id
				INNER JOIN projects
					ON time_entries.project_id = projects.id
				LEFT JOIN (
					SELECT users2.id AS users_id2, custom_values2.value AS code 
					FROM users AS users2 
					INNER JOIN custom_values AS custom_values2 
						ON users2.id = custom_values2.customized_id 
					WHERE custom_values2.custom_field_id = ' . intval($user_code_saphir_custom_field_id) . '
					) AS users_codes_saphir
					ON users.id = users_codes_saphir.users_id2
			WHERE saphir_autos.customized_type = "Principal"
				AND saphir_autos.custom_field_id = ' . intval($saphir_auto_custom_field_id) . '
				AND saphir_autos.value = 1
				AND users.status != 3
				AND projects_codes_saphir.customized_type = "Project"
				AND projects_codes_saphir.custom_field_id = ' . intval($project_code_saphir_custom_field_id) . '
				AND spent_on BETWEEN "' . $begin . '" AND "' . $end .'"
				AND time_entries.hours > 0 
			GROUP BY users.mail, project_code_saphir
			ORDER BY users.mail ASC, heure_totale ASC';
		
		$res = mysql_query($query, $this->link);
		if(!$res) {
			throw new CustomMySQLException('getUserProjectInfos() ' . mysql_error($this->link));
		}
		
		$datas = array();

		while($data = mysql_fetch_array($res)) {
			$email = $data['mail'];
			unset($data['mail']);
			$datas[$email][] = $data;
		}
		
		return $datas;
	}
	
	
	/**
	 * 
	 * Retourne la duree totale travaillee pour chaque utilisateur actif dans Redmine et qui ont la case Saphir Auto cochee
	 * Y compris les absences
	 * 
	 * @param $begin Date de debut
	 * @param $end Date de fin
	 * @throws CustomMySQLException
	 */
	public function getUserTotalTimeLogged($begin, $end) {
		$project_code_saphir_custom_field_id = sfConfig::get('mod_importredmine_project_code_saphir_custom_field_id');
		$saphir_auto_custom_field_id = sfConfig::get('mod_importredmine_saphir_auto_custom_field_id');
		
		$begin = mysql_real_escape_string($begin);
		$end = mysql_real_escape_string($end);
		$query = 'SELECT users.mail, log_times.duree_projets FROM users
			INNER JOIN custom_values AS saphir_autos
			ON users.id = saphir_autos.customized_id
				INNER JOIN (
					SELECT time_entries.user_id AS userid, ROUND(SUM(hours),2) AS duree_projets 
					FROM time_entries
					INNER JOIN custom_values AS codes_saphir
						ON time_entries.project_id = codes_saphir.customized_id
					WHERE codes_saphir.customized_type = "Project"
						AND codes_saphir.custom_field_id = ' . intval($project_code_saphir_custom_field_id) . '
						AND spent_on BETWEEN "'.$begin.'" AND "'.$end.'"
						AND time_entries.hours > 0
					GROUP BY time_entries.user_id
				) AS log_times
				ON users.id = log_times.userid
			WHERE saphir_autos.customized_type = "Principal"
				AND saphir_autos.custom_field_id = ' . intval($saphir_auto_custom_field_id) . '
				AND saphir_autos.value = 1
				AND users.status != 3
			ORDER BY users.mail ASC';
		
		$res = mysql_query($query, $this->link);
		if(!$res) {
			throw new CustomMySQLException('getUserTotalTimeLogged() ' . mysql_error($this->link));
		}
		
		$datas = array();

		while($data = mysql_fetch_array($res)) {
			$datas[$data['mail']] = $data['duree_projets'];
		}
		
		return $datas;
	}
	
	
	/**
	 * 
	 * Retourne la duree totale travaillee pour chaque utilisateur actif dans Redmine et qui ont la case Saphir Auto cochee
	 * Sans les absences
	 * 
	 * @param $begin Date de debut
	 * @param $end Date de fin
	 * @throws CustomMySQLException
	 */
	public function getUserTotalTimeLoggedWithoutAbsence($begin, $end) {
		$project_code_saphir_custom_field_id = sfConfig::get('mod_importredmine_project_code_saphir_custom_field_id');
		$saphir_auto_custom_field_id = sfConfig::get('mod_importredmine_saphir_auto_custom_field_id');
		
		$begin = mysql_real_escape_string($begin);
		$end = mysql_real_escape_string($end);
		$query = 'SELECT users.mail, log_times.duree_projets FROM users
			INNER JOIN custom_values AS saphir_autos
			ON users.id = saphir_autos.customized_id
				INNER JOIN (
					SELECT time_entries.user_id AS userid, ROUND(SUM(hours),2) AS duree_projets 
					FROM time_entries
					INNER JOIN custom_values AS codes_saphir
						ON time_entries.project_id = codes_saphir.customized_id
					WHERE codes_saphir.customized_type = "Project"
						AND codes_saphir.custom_field_id = ' . intval($project_code_saphir_custom_field_id) . '
						AND codes_saphir.value != "' . Constants::CODE_ABSENCE . '"
						AND spent_on BETWEEN "'.$begin.'" AND "'.$end.'"
						AND time_entries.hours > 0 
					GROUP BY time_entries.user_id
				) AS log_times
				ON users.id = log_times.userid
			WHERE saphir_autos.customized_type = "Principal"
				AND saphir_autos.custom_field_id = ' . intval($saphir_auto_custom_field_id) . '
				AND saphir_autos.value = 1
				AND users.status != 3
			ORDER BY users.mail ASC';
		
		$res = mysql_query($query, $this->link);
		if(!$res) {
			throw new CustomMySQLException('getUserTotalTimeLoggedWithoutAbsence() ' . mysql_error($this->link));
		}
		
		$datas = array();

		while($data = mysql_fetch_array($res)) {
			$datas[$data['mail']] = $data['duree_projets'];
		}
		
		return $datas;
	}
	
	
	
	/**
	 * 
	 * Recupere la liste des absences par utilisateur actif dans Redmine et qui ont la case Saphir Auto cochee
	 * Prend aussi en compte les differents absences d'une meme journee
	 * 
	 * @param $begin
	 * @param $end
	 * @throws CustomMySQLException
	 */
	public function getUserAbsences($begin, $end) {
		$begin = mysql_real_escape_string($begin);
		$end = mysql_real_escape_string($end);
		$query = 'SELECT users.mail, codes_saphir.value AS code_saphir, time_entries.spent_on, ROUND(SUM(time_entries.hours),2) AS heure_totale
			FROM time_entries
			INNER JOIN users
				ON time_entries.user_id = users.id
			INNER JOIN custom_values AS saphir_autos
				ON users.id = saphir_autos.customized_id
			INNER JOIN custom_values AS codes_saphir
				ON time_entries.project_id = codes_saphir.customized_id
			WHERE codes_saphir.customized_type = "Project"
			    AND codes_saphir.value = "' . Constants::CODE_ABSENCE . '"
			    AND users.status != 3
			    AND saphir_autos.customized_type = "Principal"
			    AND saphir_autos.value = 1
			    AND spent_on BETWEEN "'.$begin.'" AND "'.$end.'"
			    AND time_entries.hours > 0 
			GROUP BY users.mail, time_entries.spent_on';
		
		$res = mysql_query($query, $this->link);
		if(!$res) {
			throw new CustomMySQLException('getUserAbsences() ' . mysql_error($this->link));
		}
		
		$datas = array();

		while($data = mysql_fetch_array($res)) {
			$email = $data['mail'];
			unset($data['mail']);
			$datas[$email][] = $data;
		}
		
		return $datas;
	}
	
	
	public function close_db() {
		mysql_close($this->link);
	}
// Début modification **	
	// Récupère la liste des User Story Redmine avec le nom du projet et le nombre d'uo qui leur sont associés, 
	// l'adresse mail de l'utilisateur ayant travaillé sur la US,
	// ainsi l'année et le mois du commencement de la US.
	public function  ChargerProjets(){
	//	$projet = mysql_real_escape_string($projet);
	
	
		$query = 'select 
		name as nom,subject as sujet,  sum(hours) as heure, mail, tyear, tmonth
		from time_entries,projects,issues,users
		where projects.id = time_entries.project_id
		and issues.id=time_entries.issue_id
		and users.id = time_entries.user_id
		group by name,subject,mail,tyear,tmonth';


		 
	    $res = mysql_query($query, $this->link);
		if(!$res) {
			throw new CustomMySQLException('getProjets() ' . mysql_error($this->link));
		}
		
		
		$datas = array();
		$i= 0;
		while($data = mysql_fetch_array($res)) {		
			$datas[$i]["nom"] = $data['nom'];
			$datas[$i]["sujet"] = $data['sujet'];
			$datas[$i]["heure"] = $data['heure'];
			$datas[$i]["mail"] = $data['mail'];
			$datas[$i]["tyear"] = $data['tyear'];
			$datas[$i]["tmonth"] = $data['tmonth'];
			$i++;
		}
		return $datas;
	}
	
public function  getProjets($id, $year, $month){
	//	$projet = mysql_real_escape_string($projet);
	
	
		$query = 'select 
		name as nom,subject as sujet,  hours as heure, mail, tyear, tmonth
		from time_entries,projects,issues,users
		where projects.id = time_entries.project_id
		and issues.id=time_entries.issue_id
		and users.id = time_entries.user_id
		and users.id = '.$id.'
		and tyear = '.$year.' and tmonth = '.$month.'';


		 
	    $res = mysql_query($query, $this->link);
		if(!$res) {
			throw new CustomMySQLException('getProjets() ' . mysql_error($this->link));
		}
		
		
		$datas = array();
		$i= 0;
		while($data = mysql_fetch_array($res)) {		
			$datas[$i]["nom"] = $data['nom'];
			$datas[$i]["sujet"] = $data['sujet'];
			$datas[$i]["heure"] = $data['heure'];
			$datas[$i]["mail"] = $data['mail'];
			$datas[$i]["tyear"] = $data['tyear'];
			$datas[$i]["tmonth"] = $data['tmonth'];
			$i++;
		}
		return $datas;
	}
	
	
	public function tempsProjets($id,$year,$month){
		$query = 'select  name as nom, sum(hours) as heure
		from projects,users,time_entries 
    	where  time_entries.project_id =projects.id
		and    time_entries.user_id  = users.id
		and  time_entries.tyear = '.$year.'
    	and time_entries.tmonth = '.$month.'
		and time_entries.user_id = '.$id.'
    	group by nom;'; 

		$res = mysql_query($query, $this->link);
		
		if(!$res) {
			throw new CustomMySQLUOException('getProjets() ' . mysql_error($this->link));
		}
		
		$bilans = array();
		$i= 0;
		while($bilan = mysql_fetch_array($res)) {		
			$bilans[$i]["nom"] = $bilan['nom'];
			$bilans[$i]["heure"] = $bilan['heure']; 
			$i++;
		}
		return $bilans;
		
	}
// Fin modification **
}