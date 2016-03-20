<?php

class RedmineUOWrapperMysql {
	
	private 
		$host,
		$port,
		$user,
		$pass,
		$database,
		$link,
		$projet;
	
	/**
	 * 
	 * Connexion au serveur MYSQL de Redmine et selection de la base de donnees
	 */
	public function __construct() {
		$this->host = sfConfig::get('mod_main_host');
		$this->port = sfConfig::get('mod_main_port');
		$this->user = sfConfig::get('mod_main_user');
		$this->pass = sfConfig::get('mod_main_pass');
		$this->database = sfConfig::get('mod_main_database');		
		$this->link = mysql_connect($this->host . ':' . $this->port, $this->user, $this->pass);
	 
		if(!$this->link)
			throw new CustomUOMySQLException('Connexion impossible a la BDD : ' . mysql_error($this->link));
		 
		$base = mysql_select_db($this->database, $this->link);	 
		if (!$base)
			throw new CustomUOMySQLException(mysql_error($this->link));
	}
	
	//R�cup�re des informations(projet, titre, duree, date) d'un projet selectionn�

	public function getIdUser($email)
	{
		$query = 'select id from users where mail = '.$email.'';
		
	$res = mysql_query($query, $this->link);
		if(!$res) {
			throw new CustomMySQLUOException('getIdUser ' . mysql_error($this->link));
		}
		
		$data = mysql_result($res);
		
		return $data;
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
	
	
	public function close_db() {
		mysql_close($this->link);
	}
}