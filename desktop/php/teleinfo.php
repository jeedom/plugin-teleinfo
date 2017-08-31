<?php
if (!isConnect('admin')) {
throw new Exception('{{401 - Accès non autorisé}}');
}
sendVarToJS('eqType', 'teleinfo');
$eqLogics = eqLogic::byType('teleinfo');
?>
<div class="row row-overflow">
	<link rel="stylesheet" href="https://openlayers.org/en/v4.1.1/css/ol.css" type="text/css">
	<script src="https://openlayers.org/en/v4.1.1/build/ol.js"></script>
	<div class="col-lg-2">
		<div class="bs-sidebar">
			<ul id="ul_eqLogic" class="nav nav-list bs-sidenav">
				<a class="btn btn-default eqLogicAction" style="width : 50%;margin-top : 5px;margin-bottom: 5px;" data-action="add"><i class="fa fa-plus-circle"></i> {{Ajouter}}</a>
				<li class="filter" style="margin-bottom: 5px;"><input class="filter form-control input-sm" placeholder="{{Rechercher}}" style="width: 100%"/></li>
				<?php
					foreach ($eqLogics as $eqLogic) 
						echo '<li class="cursor li_eqLogic" data-eqLogic_id="' . $eqLogic->getId() . '"><a>' . $eqLogic->getHumanName(true) . '</a></li>';
				?>
			</ul>
		</div>
	</div>
	<div class="col-lg-10 col-md-9 col-sm-8 eqLogicThumbnailDisplay" style="border-left: solid 1px #EEE; padding-left: 25px;">
		<legend>{{Gestion}}</legend>
		<div class="eqLogicThumbnailContainer">
			<!--<div class="cursor" id="bt_cout" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
			  <center>
				<i class="fa fa-eur" style="font-size : 5em;color:#767676;"></i>
			  </center>
			  <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Coût}}</center></span>
			</div>-->
			
			<div class="cursor" id="bt_info_daemon" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
			  <center>
				<i class="fa fa-heartbeat" style="font-size : 5em;color:#767676;"></i>
			  </center>
			  <span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>{{Info Modem}}</center></span>
			</div>
			
			<div class="cursor eqLogicAction" data-action="gotoPluginConf" style="background-color : #ffffff; height : 140px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;">
				<center>
					<i class="fa fa-wrench" style="font-size : 5em;color:#767676;"></i>
				</center>
			<span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#767676"><center>Configuration</center></span>
			</div>
			
			
		</div>	
        <legend>{{Mes Modules de Téléinformation}}</legend>
        <div class="eqLogicThumbnailContainer">
			<div class="cursor eqLogicAction" data-action="add" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >
				<center>
					<i class="fa fa-plus-circle" style="font-size : 7em;color:#4F81BD;"></i>
				</center>
				<span style="font-size : 1.1em;position:relative; top : 23px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;color:#4F81BD"><center>Ajouter</center></span>
			</div>
		
			<?php
			foreach ($eqLogics as $eqLogic) {
				echo '<div class="eqLogicDisplayCard cursor" data-eqLogic_id="' . $eqLogic->getId() . '" style="background-color : #ffffff; height : 200px;margin-bottom : 10px;padding : 5px;border-radius: 2px;width : 160px;margin-left : 10px;" >';
				echo "<center>";
				echo '<img src="plugins/teleinfo/doc/images/teleinfo_icon.png" height="105" width="95" />';
				echo "</center>";
				echo '<span style="font-size : 1.1em;position:relative; top : 15px;word-break: break-all;white-space: pre-wrap;word-wrap: break-word;"><center>' . $eqLogic->getHumanName(true, true) . '</center></span>';
				echo '</div>';
			}
			?>
		</div>
    </div>
	<div class="col-lg-10 col-md-9 col-sm-8 eqLogic" style="border-left: solid 1px #EEE; padding-left: 25px;display: none;">
		<a class="btn btn-success btn-sm eqLogicAction pull-right" data-action="save"><i class="fa fa-check-circle"></i> Sauvegarder</a>
		<a class="btn btn-danger btn-sm eqLogicAction pull-right" data-action="remove"><i class="fa fa-minus-circle"></i> Supprimer</a>
		<a class="btn btn-default btn-sm eqLogicAction pull-right" data-action="configure"><i class="fa fa-cogs"></i></a>
		<a class="btn btn-default btn-sm eqLogicAction pull-right expertModeVisible " data-action="copy"><i class="fa fa-copy"></i></a>
		<ul class="nav nav-tabs" role="tablist">
			<li role="presentation">
				<a href="#" class="eqLogicAction" aria-controls="home" role="tab" data-toggle="tab" data-action="returnToThumbnailDisplay">
					<i class="fa fa-arrow-circle-left"></i>
				</a>
			</li>
			<li role="presentation" class="active">
				<a href="#eqlogictab" aria-controls="home" role="tab" data-toggle="tab" aria-expanded="true">
					<i class="fa fa-tachometer"></i> Equipement</a>
			</li>
			<li role="presentation" class="">
				<a href="#commandtab" aria-controls="profile" role="tab" data-toggle="tab" aria-expanded="false">
					<i class="fa fa-list-alt"></i> Commandes</a>
			</li>
		</ul>
			<div class="tab-content" style="height:calc(100% - 50px);overflow:auto;overflow-x: hidden;">
				<div role="tabpanel" class="tab-pane active" id="eqlogictab">
					<div class="col-sm-6">
						<form class="form-horizontal">
							<legend>Général</legend>
							<fieldset>
								<div class="form-group ">
									<label class="col-sm-2 control-label">{{Nom de l'équipement}}
										<sup>
											<i class="fa fa-question-circle tooltips" title="Indiquer le nom de votre zone" style="font-size : 1em;color:grey;"></i>
										</sup>
									</label>
									<div class="col-sm-5">
										<input type="text" class="eqLogicAttr form-control" data-l1key="id" style="display : none;" />
										<input type="text" class="eqLogicAttr form-control" data-l1key="name" placeholder="{{Nom du groupe de zones}}"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-2 control-label" >{{Objet parent}}
										<sup>
											<i class="fa fa-question-circle tooltips" title="Indiquer l'objet dans lequel le widget de cette zone apparaîtra sur le Dashboard" style="font-size : 1em;color:grey;"></i>
										</sup>
									</label>
									<div class="col-sm-5">
										<select id="sel_object" class="eqLogicAttr form-control" data-l1key="object_id">
											<option value="">{{Aucun}}</option>
											<?php
												foreach (object::all() as $object) 
													echo '<option value="' . $object->getId() . '">' . $object->getName() . '</option>';
											?>
										</select>
									</div>
								</div>
								<div class="form-group">
									<label class="col-md-2 control-label">
										{{Catégorie}}
										<sup>
											<i class="fa fa-question-circle tooltips" title="Choisissez une catégorie
	Cette information n'est pas obigatoire mais peut être utile pour filtrer les widgets" style="font-size : 1em;color:grey;"></i>
										</sup>
									</label>
									<div class="col-md-8">
										<?php
										foreach (jeedom::getConfiguration('eqLogic:category') as $key => $value) {
											echo '<label class="checkbox-inline">';
											echo '<input type="checkbox" class="eqLogicAttr" data-l1key="category" data-l2key="' . $key . '" />' . $value['name'];
											echo '</label>';
										}
										?>

									</div>
								</div>
								<div class="form-group">
									<label class="col-sm-2 control-label" >
										{{Etat du widget}}
										<sup>
											<i class="fa fa-question-circle tooltips" title="Choisissez les options de visibilité et d'activation
	Si l'équipement n'est pas activé, il ne sera pas utilisable dans Jeedom ni visible sur le Dashboard
	Si l'équipement n'est pas visible, il sera caché sur le Dashboard" style="font-size : 1em;color:grey;"></i>
										</sup>
									</label>
									<div class="col-sm-5">
										<label>{{Activer}}</label>
										<input type="checkbox" class="eqLogicAttr" data-label-text="{{Activer}}" data-l1key="isEnable" checked/>
										<label>{{Visible}}</label>
										<input type="checkbox" class="eqLogicAttr" data-label-text="{{Visible}}" data-l1key="isVisible" checked/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-lg-4 control-label">{{Identifiant Compteur}}
										<sup>
											<i class="fa fa-question-circle tooltips" title="Non obligatoire sera mis a jours automatiquement par le plugin" style="font-size : 1em;color:grey;"></i>
										</sup></label>
									<div class="col-lg-8">
										<input type="text" class="eqLogicAttr form-control tooltips" title="{{Identifiant du compteur aussi connu sous le nom ADCO.}}" data-l1key="logicalId" placeholder="{{ADCO du compteur}}"/>
									</div>
								</div>
								<div class="form-group">
									<label class="col-lg-4 control-label">{{Port du modem}}
										<sup>
											<i class="fa fa-question-circle tooltips" title="Séléctioner le port du modem" style="font-size : 1em;color:grey;"></i>
										</sup></label>
									<div class="col-lg-8">
										<select id="select_port" class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="port">
											<option value="">Aucun</option>
											<?php
											foreach (jeedom::getUsbMapping() as $name => $value) {
												echo '<option value="' . $value . '">' . $name . ' (' . $value . ')</option>';
											}
											echo '<option value="serie">Modem Série</option>';
											?>
										</select>
									</div>
									<div class="col-lg-8">
										<input class="eqLogicAttr form-control" data-l1key="configuration" data-l2key="modem_serie_addr" style="margin-top:5px;display:none" placeholder="Renseigner le port série (ex : /dev/ttyS0)"/>
									</div>
									<div class="col-lg-8">
										<label>{{Utiliser le deuxieme compteur}}</label>
										<input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="2cmpt"/>
									</div>
								</div>
							</fieldset>
						</form>
					</div>
					<div class="col-sm-6 Present">
						<form class="form-horizontal">
							<legend>Gestion de la présence</legend>
							<fieldset>
								<legend>{{Paramètres}}</legend>
								<div class="form-group">
									<div class="col-lg-12">
										<p>{{Attention, il est nécessaire d'activer l'historisation des index pour utiliser les statistiques}}</p>
									</div>
								</div>
								<div class="form-group">
									<label class="col-md-3 control-label pull-left">{{Votre abonnement :}}</label>
									<div class="col-md-3">
										<select class="eqLogicAttr form-control tooltips" title="{{Abonnement présent sur le compteur}}" data-l1key="configuration" data-l2key="abonnement">
										<option value="">Aucun</option>
										<option value="base">Base (HP)</option>
										<option value="basetri">Base triphasé</option>
										<option value="bleu">Bleu (HP/HC)</option>
										<option value="bleutri">Bleu triphasé</option>
										<option value="tempo">Tempo / EJP</option>
										<option value="tempotri">Tempo triphasé</option>
										</select>
									</div>
									<label class="col-md-2 control-label">{{Commandes :}}</label>
									<div class="col-md-2 tooltips" title="{{Créer automatiquement les commandes envoyées par le compteur}}">
										<label class="checkbox-inline"><input type="checkbox" class="eqLogicAttr" data-l1key="configuration" data-l2key="AutoCreateFromCompteur" checked/>{{Auto}}</label>
									</div>
									<!--<div class="col-md-2">
										<input class="eqLogicAttr" style="display:none" type="checkbox"  data-l1key="configuration" data-l2key="AutoGenerateFields" id="checkbox-autocreate"/>
										<a class="btn btn-info btn-sm eqLogicAction tooltips"  id="create_data_teleinfo" title="{{Permet de créer automatiquement les commandes nécessaires.}}" id="createcmd"><i class="fa fa-plus-circle"></i> {{Créer}}</a><br/><br/>
									</div>-->
								</div>
								<div class="form-group">
									<!--<label class="col-md-3 control-label pull-left">{{Choix du template :}}</label>
									<div class="col-md-3">
										<select class="eqLogicAttr form-control tooltips" title="{{FONCTION BETA - Modèle à utiliser pour l'affichage}}" data-l1key="configuration" data-l2key="template">
										<option value="">Aucun</option>
										<option value="base">Base</option>
										<option value="bleu">Bleu</option>
										</select>
									</div>-->
									<div class="col-md-3 col-md-offset-3">
										<a class="btn btn-info tooltips"  id="bt_teleinfoHealth" title="{{Informations sur les données}}"><i class="fa fa-medkit"></i>{{ Santé}}</a>
									</div>
									<!--<div class="col-md-2">
										<a class="btn btn-info btn-sm eqLogicAction tooltips"  data-action="save" title="{{Applique le template}}"><i class="fa fa-plus-circle"></i> {{Appliquer}}</a><br/><br/>
									</div>-->
								</div>
								<!--<div class="form-group">
									<label class="col-md-3 control-label pull-left">{{Unités d'affichage :}}</label>
									<div class="col-md-3">
										<select class="eqLogicAttr form-control tooltips" title="{{FONCTION BETA - NE FONCTIONNE PAS ACTUELLEMENT}}" data-l1key="configuration" data-l2key="unites">
										<option value="">Sans modifications</option>
										<option value="wh">Wh</option>
										<option value="kwh">kWh</option>
										</select>
									</div>
								</div>-->
							</fieldset>
						</form>
					</div>
				</div>		
				<div role="tabpanel" class="tab-pane" id="commandtab">	
					<table id="table_cmd" class="table table-bordered table-condensed">
					    <thead>
						<tr>
							<th style="width: 50px;">#</th>
							<th style="width: 150px;">{{Nom}}</th>
							<th style="width: 110px;">{{Sous-Type}}</th>
							<th style="width: 200px;">{{Donnée}}</th>
							<th style="width: 150px;">{{Paramètres}}</th>
							<th style="width: 150px;"></th>
						</tr>
					    </thead>
					    <tbody></tbody>
					</table>
				</div>	
			</div>
		</div>
</div>

<?php include_file('desktop', 'teleinfo', 'js', 'teleinfo'); ?>
<?php include_file('core', 'plugin.template', 'js'); ?>
