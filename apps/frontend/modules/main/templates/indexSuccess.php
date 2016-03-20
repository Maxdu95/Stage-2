<?php
echo init_saphir($user->getNbheureJour(), $month, $year, url_for('main/day'), url_for('main/details'), $user_id);
?>

<?php
	if ($sf_user->getAttribute('utilisateur')->getRole() == 2) {
		use_helper('Object');
		echo select_tag('utilisateur', objects_for_select($users, 'getId', 'getNomPrenom', $user_id), array(
			'id'=>'main_utilisateur', 'onchange' => "window.location = '".url_for('main/index')."/date/$year/$month/' + this.options[this.selectedIndex].value"
		));
	}
?>

<table id="tableCalendrier">
	<tr>
		<td class="dateSelection">
			<span class="arrondiHG"></span>
			<span class="arrondiHD"></span>
			<form>
				<p>
			       <?php
						if ($month <= 1) {
							$prevmonth = 12;
							$prevyear = $year - 1;
						} else {
							$prevmonth = $month - 1;
							$prevyear = $year;
						}
						echo link_to(image_tag('pictocalendrier_moismoins'), "main/index?month=$prevmonth&year=$prevyear&user=$user_id");
					?>

		<select id="mois_select" onchange="window.location = '<?php echo url_for('main/index'); ?>/date/' + $('annee_select').value + '/' + this.options[this.selectedIndex].value<?php if ($sf_user->getAttribute('utilisateur')->getRole()==2) echo " + '/$user_id'"; ?>;">
		<?php for ($i = 1; $i<=12; $i++) : ?>
		<option value="<?php echo $i; ?>" <?php echo ($month == $i?'selected="selected"':''); ?>><?php echo ucwords(format_date(mktime(0,0,0,$i,1,2000), 'MMMM', 'fr')); ?></option>
		<?php endfor; ?>
		</select>
					<?php
						if ($month >= 12) {
							$nextmonth = 1;
							$nextyear = $year + 1;
						} else {
							$nextmonth = $month + 1;
							$nextyear = $year;
						}
						echo link_to(image_tag('pictocalendrier_moisplus'), "main/index?month=$nextmonth&year=$nextyear&user=$user_id");
					?>
				</p>
				<p>
				
		<select id="annee_select" onchange="window.location = '<?php echo url_for('main/index'); ?>/date/' + this.options[this.selectedIndex].value + '/' + $('mois_select').value<?php if ($sf_user->getAttribute('utilisateur')->getRole()==2) echo " + '/$user_id'"; ?>;">
		<?php for ($i = (date('Y')-2); $i <= (date('Y')+2); $i++) : ?>
		<option value="<?php echo $i;?>" <?php echo ($year == $i?'selected="selected"':''); ?>><?php echo $i; ?></option>
		<?php endfor; ?>
		</select>
				</p>
			</form>
		</td>
		<!-- .dateSelection -->
		
		<td class="toolBar">
			<table cellspacing="0" class="toolContent">
			<tr>
				<td class="fcts">
					<table id="toolTable">
						<tr>
							<td><?php echo toolbox_item('details', 'btn_detail', 'DETAILS', 'Detail/Saisir'); ?></td>
							<td><img src="/images/tooltable_sep.png" alt=""/></td>
							<td><?php echo toolbox_item('eraser', 'btn_effacer', 'ERASER', 'Effacer'); ?></td>
							<td><img src="/images/tooltable_sep.png" alt=""/></td>
							<td><?php echo toolbox_item('copy', 'btn_copier', 'COPY', 'Copier'); ?> /</td>
							<td><?php echo toolbox_item('paste', 'btn_coller', 'PASTE', 'Coller'); ?></td>
							<td><img src="/images/tooltable_sep.png" alt=""/></td>
							<td><?php echo toolbox_item('painter', 'btn_saisir', 'PAINTER', 'Appliquer'); ?></td>
						</tr>
					</table><!-- #toolTable -->
			  </td>
				<td class="infos">
					<form>
						<p>
							<label for="projets">Projets</label>
							<?php
								echo select_tag('mission_select', options_for_select($missions),
									array(
										'class'=>'widthProjets',
										'id'=>'mission_select',
										'onchange'=>
											"
											$('comment').value = '';
											
											if (Env.hours > 4 && this.options[this.selectedIndex].text.substring(0,8) == 'Absences')
											{
												$('comment').hide(); $('absences').show();
												$('comment').value = $('absences').value;
											} else {
												$('comment').show(); $('absences').hide();
											}
											"
									));
							?>
						</p>
						<p>
							<label for="duree">Dur&eacute;e</label>
							<?php echo time_select($user->getNbheureJour()); ?>
							Commentaire <input name="comment" id="comment" type="text" class="widthComm" />
							<select id="absences" style="display: none; width: 140px;" onchange="$('comment').value = this.value;">
							<?php include_component('main','absences'); ?>
							</select>
						</p>
					</form>
				</td>
			  </tr>
		  </table><!-- .toolContent -->
		</td>
		
		<td class="statutExport">
			<span class="arrondiHG"></span>
			<span class="arrondiHD"></span>
			<p>Jours remplis : <?php echo filled_days('filled'); ?> / <?php echo $total_days; ?></p>
			<p><?php echo link_to(image_tag('bt_export_excel'), "main/excel?month=$month&year=$year&user=$user_id"); ?></p>
		</td>
		<!-- .statutExport-->
	</tr>
	
	
	<tr>
		<td colspan="3" >
			<table id="calendrier">
			  <tr class="entete">
				<td>Lundi</td>
				<td>Mardi</td>
				<td>Mercredi</td>
				<td>Jeudi</td>
				<td>Vendredi</td>
			  </tr>
			  
			  
			  
<?php
	foreach($days as $week)
	{
		// Saute les semaines o� les jours ouvr�s sont tous dans un autre mois
		if ( (date('n', $week[0]) <> $month) && (date('n', $week[1]) <> $month) && (date('n', $week[2]) <> $month)
			&& (date('n', $week[3]) <> $month) && (date('n', $week[4]) <> $month)) continue;
	
		echo "<tr>";
		foreach($week as $day)
		{
			$day_of_month = date('j',$day);
			if(date('w',$day) == 0 || date('w',$day) == 6)
			{
			
			}
			else
			{
					if (in_array(date('Y-m-d',$day), $open_days))
					{
						echo "<td class=\"day\" id=\"day_".date('Ymd',$day)."\" onclick=\"Calendar.click(this, '".date('Ymd',$day)."');\">";
						include_component('main','day', array('month'=>date('m',$day), 'year'=>date('Y',$day), 'day'=>date('d',$day), 'user'=>$user_id, 'hours'=>$user->getNbheureJour()));
						echo "</td>";
					}
					else
					{
						echo "<td class=\"noDate\">".date('d', $day)."</td>";
					}
			}
		}
		echo "</tr>";
	}
?>
	
	<tr>
		<td colspan="3" class="piedTable">
			<span class="arrondiBG">&nbsp;</span>
			<span class="arrondiBD">&nbsp;</span>
			&nbsp;
		</td>
	</tr>
</table>

<!-- MAXIME -->
<!--<p>&nbsp;</p>

<table id="tableau">
	<tr class = "detailsProjet">
		<td>Projet</td>
		<td>Titre</td>
		<td>Temps</td>
		<td>Periode</td>
	</tr>
	
	<tr class= "valeursDetails">
	
	<td><?php echo $datas; ?></td>
	</tr>
</table> -->

<div id="indicator" style="display:none;">
<?php echo image_tag('indicator.gif'); ?>
</div>

<?php echo infobox(array('style'=>'width:400px; border: solid 1px; background:#fff')); ?>

<?php
	echo javascript_tag('Statusbox.update(); Toolbox.setSelected(Toolbox.DETAILS)');
	
	echo javascript_tag("if (Env.hours > 4 && $('mission_select').options[$('mission_select').selectedIndex].text.substring(0,8) == 'Absences')
		{
			$('comment').hide(); $('absences').show();
			$('comment').value = $('absences').value;
		}

		Missions.initialize('mission_select', 'time_select', 'comment');");
	
	//if (isset($popup))
	//	echo javascript_tag(utf8_encode("alert('Vous n\'avez pas saisi votre pr�visionnel sur 3 mois');"));
?>

<!--  <p>&nbsp;</p>

<?php echo "Liste des tâches : ";?>
</br>
</br>
<table cellspacing='10' id="tableauProjet" width = '100%'>
	<tr class = "detailsProjet">
		<td>Projet</td>
		<td>User Story</td>
		<td>UO</td>
	</tr>
	<?php 
	echo "----------------------------------------------------   " +  $Mail;
	

		foreach ($temps as $aProject) {			
			
			echo "<tr>";
			echo "<td>";
			echo utf8_encode($aProject["nom"]);			
			echo "</td>";	
			
			echo "<td>";
			echo utf8_encode ($aProject["sujet"]);	
			echo "</td>";	
			
			echo "<td>";
			echo utf8_encode($aProject["heure"]);		
			echo "</td>";	
			echo "</tr>";
			
			
		}
	?>
</table>
</br>
</br>

<?php echo "Liste projet par utilisateur : ";?>
<table cellspacing = '10' id="tableauUO" width = '50%'>
	<tr class = "detailsUO">
		<td>Projet</td>
		<td>UO</td>
	</tr>
	</br>
	<br>
	<?php 
		foreach ($userStory as $aProject) {			
			
			echo "<tr>";
			echo "<td>";
			echo utf8_encode($aProject["nom"]);			
			echo "</td>";					
			echo "<td>";
			echo utf8_encode($aProject["heure"]);		
			echo "</td>";				
			echo "</tr>";
			
			
		}
	?>
</table> -->
<?php 
//var_dump($datas);
?>