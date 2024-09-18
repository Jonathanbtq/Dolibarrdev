<?php
/* Copyright (C) 2023 Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2024		MDW							<mdeweerd@users.noreply.github.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *  \file       htdocs/fourn/facture/agenda.php
 *  \ingroup    invoice
 *  \brief      Tab of events on Invoices Fournisseur
 */
require '../../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.class.php';
require_once DOL_DOCUMENT_ROOT.'/fourn/class/fournisseur.facture.class.php';
require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/invoice.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/functions2.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/fourn.lib.php';


// Load translation files required by the page
$langs->loadLangs(array("bills", "other"));

// Get parameters
$id = GETPOSTINT('id');
$ref = GETPOST('ref', 'alpha');
$action = GETPOST('action', 'aZ09');
$cancel = GETPOST('cancel', 'aZ09');
$contextpage = GETPOST('contextpage', 'aZ') ? GETPOST('contextpage', 'aZ') : str_replace('_', '', basename(dirname(__FILE__)).basename(__FILE__, '.php')); // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha');

if (GETPOST('actioncode', 'array')) {
	$actioncode = GETPOST('actioncode', 'array', 3);
	if (!count($actioncode)) {
		$actioncode = '0';
	}
} else {
	$actioncode = GETPOST("actioncode", "alpha", 3) ? GETPOST("actioncode", "alpha", 3) : (GETPOST("actioncode") == '0' ? '0' : getDolGlobalString('AGENDA_DEFAULT_FILTER_TYPE_FOR_OBJECT'));
}
$search_rowid = GETPOST('search_rowid');
$search_agenda_label = GETPOST('search_agenda_label');

$limit = GETPOSTINT('limit') ? GETPOSTINT('limit') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOSTINT('pageplusone') - 1) : GETPOSTINT("page");
if (empty($page) || $page == -1) {
	$page = 0;
}     // If $page is not defined, or '' or -1
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (!$sortfield) {
	$sortfield = 'a.datep,a.id';
}
if (!$sortorder) {
	$sortorder = 'DESC,DESC';
}

// Initialize a technical objects
$object = new FactureFournisseur($db);
$extrafields = new ExtraFields($db);
$diroutputmassaction = $conf->facture->multidir_output[$conf->entity].'/temp/massgeneration/'.$user->id;

// Initialize a technical object to manage hooks of page. Note that conf->hooks_modules contains an array of hook context
$hookmanager->initHooks(array('invoiceagenda', 'globalcard')); // Note that conf->hooks_modules contains array

$object = new FactureFournisseur($db);
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extrafields->fetch_name_optionals_label($object->table_element);

// Load object
if ($id > 0 || !empty($ref)) {
	$ret = $object->fetch($id, $ref);
	if ($ret < 0) {
		dol_print_error($db, $object->error);
	}
	$ret = $object->fetch_thirdparty();
	if ($ret < 0) {
		dol_print_error($db, $object->error);
	}
}
// Security check
$socid = GETPOSTINT('socid');
if (!empty($user->socid)) {
	$socid = $user->socid;
}

$usercancreate = false;

$isdraft = (($object->status == FactureFournisseur::STATUS_DRAFT) ? 1 : 0);
$result = restrictedArea($user, 'fournisseur', $id, 'facture_fourn', 'facture', 'fk_soc', 'rowid', $isdraft);


/*
 *  Actions
 */

$parameters = array('id' => $id);
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}

if (empty($reshook)) {
	// Cancel
	if (GETPOST('cancel', 'alpha') && !empty($backtopage)) {
		header("Location: ".$backtopage);
		exit;
	}

	// Purge search criteria
	if (GETPOST('button_removefilter_x', 'alpha') || GETPOST('button_removefilter.x', 'alpha') || GETPOST('button_removefilter', 'alpha')) { // All tests are required to be compatible with all browsers
		$actioncode = '';
		$search_agenda_label = '';
	}
}



/*
 *	View
 */

$form = new Form($db);

if ($object->id > 0) {
	$title = $langs->trans("Agenda");
	$help_url = 'EN:Module_Agenda_En|DE:Modul_Terminplanung';
	llxHeader('', $title, $help_url);

	if (isModEnabled('notification')) {
		$langs->load("mails");
	}
	$head = facturefourn_prepare_head($object);
	$titre = $langs->trans('SupplierInvoice');
	print dol_get_fiche_head($head, 'agenda', $titre, -1, 'supplier_invoice', 0, '', '', 0, '', 1);

	// Object card
	// ------------------------------------------------------------
	$linkback = '<a href="'.DOL_URL_ROOT.'/compta/facture/list.php?restore_lastsearch_values=1'.(!empty($socid) ? '&socid='.$socid : '').'">'.$langs->trans("BackToList").'</a>';

	$morehtmlref = '<div class="refidno">';
	// Ref customer
	$morehtmlref .= $form->editfieldkey("RefSupplierBill", 'ref_supplier', $object->ref_supplier, $object, $usercancreate, 'string', '', 0, 1);
	$morehtmlref .= $form->editfieldval("RefSupplierBill", 'ref_supplier', $object->ref_supplier, $object, $usercancreate, 'string', '', null, null, '', 1);
	// Thirdparty
	$morehtmlref .= '<br>'.$object->thirdparty->getNomUrl(1, 'supplier');
	if (!getDolGlobalString('MAIN_DISABLE_OTHER_LINK') && $object->thirdparty->id > 0) {
		$morehtmlref .= ' <div class="inline-block valignmiddle">(<a class="valignmiddle" href="'.DOL_URL_ROOT.'/fourn/facture/list.php?socid='.((int) $object->thirdparty->id).'&search_company='.urlencode($object->thirdparty->name).'">'.$langs->trans("OtherBills").'</a>)</div>';
	}
	// Project
	if (isModEnabled('project')) {
		$langs->load("projects");
		$morehtmlref .= '<br>';
		if (0) {
			$morehtmlref .= img_picto($langs->trans("Project"), 'project', 'class="pictofixedwidth"');
			if ($action != 'classify') {
				$morehtmlref .= '<a class="editfielda" href="'.$_SERVER['PHP_SELF'].'?action=classify&token='.newToken().'&id='.$object->id.'">'.img_edit($langs->transnoentitiesnoconv('SetProject')).'</a> ';
			}
			$morehtmlref .= $form->form_project($_SERVER['PHP_SELF'].'?id='.$object->id, $object->socid, $object->fk_project, ($action == 'classify' ? 'projectid' : 'none'), 0, 0, 0, 1, '', 'maxwidth300');
		} else {
			if (!empty($object->fk_project)) {
				$proj = new Project($db);
				$proj->fetch($object->fk_project);
				$morehtmlref .= $proj->getNomUrl(1);
				if ($proj->title) {
					$morehtmlref .= '<span class="opacitymedium"> - '.dol_escape_htmltag($proj->title).'</span>';
				}
			}
		}
	}
	$morehtmlref .= '</div>';


	dol_banner_tab($object, 'ref', $linkback, 1, 'ref', 'ref', $morehtmlref);

	print '<div class="fichecenter">';
	print '<div class="underbanner clearboth"></div>';

	$object->info($object->id);
	dol_print_object_info($object, 1);

	print '</div>';

	print dol_get_fiche_end();



	// Actions buttons

	$objthirdparty = $object;
	$objcon = new stdClass();

	$out = '&origin='.urlencode((string) ($object->element.(property_exists($object, 'module') ? '@'.$object->module : ''))).'&originid='.urlencode((string) ($object->id));
	$urlbacktopage = $_SERVER['PHP_SELF'].'?id='.$object->id;
	$out .= '&backtopage='.urlencode($urlbacktopage);
	$permok = $user->hasRight('paymentbagendaybanktransfer', 'myactions', 'create');
	if ((!empty($objthirdparty->id) || !empty($objcon->id)) && $permok) {
		//$out.='<a href="'.DOL_URL_ROOT.'/comm/action/card.php?action=create';
		if (get_class($objthirdparty) == 'Societe') {
			$out .= '&socid='.urlencode((string) ($objthirdparty->id));
		}
		$out .= (!empty($objcon->id) ? '&contactid='.urlencode($objcon->id) : '');
		//$out.=$langs->trans("AddAnAction").' ';
		//$out.=img_picto($langs->trans("AddAnAction"),'filenew');
		//$out.="</a>";
	}

	$morehtmlright = '';

	$messagingUrl = DOL_URL_ROOT.'/fourn/facture/messaging.php?id='.$object->id;
	$morehtmlright .= dolGetButtonTitle($langs->trans('ShowAsConversation'), '', 'fa fa-comments imgforviewmode', $messagingUrl, '', 1);
	$messagingUrl = DOL_URL_ROOT.'/fourn/facture/agenda.php?id='.$object->id;
	$morehtmlright .= dolGetButtonTitle($langs->trans('MessageListViewType'), '', 'fa fa-bars imgforviewmode', $messagingUrl, '', 2);

	if (isModEnabled('agenda')) {
		if ($user->hasRight('agenda', 'myactions', 'create') || $user->hasRight('agenda', 'allactions', 'create')) {
			$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out);
		} else {
			$morehtmlright .= dolGetButtonTitle($langs->trans('AddAction'), '', 'fa fa-plus-circle', DOL_URL_ROOT.'/comm/action/card.php?action=create'.$out, '', 0);
		}
	}


	if (isModEnabled('agenda') && ($user->hasRight('agenda', 'myactions', 'read') || $user->hasRight('agenda', 'allactions', 'read'))) {
		print '<br>';

		$param = '&id='.$object->id.(!empty($socid) ? '&socid='.$socid : '');
		if (!empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) {
			$param .= '&contextpage='.urlencode($contextpage);
		}
		if ($limit > 0 && $limit != $conf->liste_limit) {
			$param .= '&limit='.((int) $limit);
		}

		// Try to know count of actioncomm from cache
		require_once DOL_DOCUMENT_ROOT.'/core/lib/memory.lib.php';
		$cachekey = 'count_events_facture_'.$object->id;
		$nbEvent = dol_getcache($cachekey);

		print load_fiche_titre($langs->trans("ActionsOnBill"), $morehtmlright, '');

		// List of all actions
		$filters = array();
		$filters['search_agenda_label'] = $search_agenda_label;
		$filters['search_rowid'] = $search_rowid;

		// TODO Replace this with same code than into list.php
		show_actions_done($conf, $langs, $db, $object, null, 0, $actioncode, '', $filters, $sortfield, $sortorder, property_exists($object, 'module') ? $object->module : '');
	}
}

// End of page
llxFooter();
$db->close();
