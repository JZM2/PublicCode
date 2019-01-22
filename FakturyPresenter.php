<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

namespace App\Presenters;

use Nette\Application\UI;
use Nette\Templating\FileTemplate;

/**
 * Presenter for Invoices
 */
class FakturyPresenter extends BasePresenter {

	/** @var Model\FakturyManager */
	private $fakturyManager;

	/** @persistent */
	public $year;

	/**
	 * Constructor
	 * @param \App\Model\FakturaManager $fakturyManager
	 */
	public function __construct(\App\Model\FakturaManager $fakturyManager) {
		$this->fakturyManager = $fakturyManager;

		if ($this->year == '')
			$this->year = date("Y");

		parent::__construct();
	}

	/**
	 * Startup function, Allowed for users
	 */
	public function startup() {
		parent::startup();

		if (!$this->user->isAllowed('faktury')) {
			$this->flashMessage('Přístup zamítnut');
			$this->redirect('Homepage:');
		}
	}

	/**
	 * Render default homepage
	 */
	public function renderDefault() {
		$this->template->year = $this->year;
		$this->template->invoices = $this->fakturyManager->Get($this->year);

		//$this['invoiceForm']->setDefault
	}

	/**
	 * not implement
	 */
	public function renderNew() {
		
	}

	/**
	 * View for InvoiceItemEdit
	 * @param type $idi
	 */
	public function renderPolozkaedit($idi) {
		if ($idi < 1) {
			$this->flashMessage("ID položky je neplatné!");
			$this->redirect('Faktury');
		}


		$item = $this->fakturyManager->GetInvoiceItemFromID($idi);

		$this['invoiceItemEditForm']->setDefaults($item);
	}

	/**
	 * Action for generate PDF
	 * @param type $idf
	 */
	public function actionDodakPDF($idf) {
		$pdf = new \Mpdf\Mpdf(array('orientation' => 'P'));

		$pdf->ignore_invalid_utf8 = true;

		$pdf->SetHTMLFooter('
<table width="100%" style="border-top: 0.3mm solid black;">
    <tr>
        <td width="25%" style="font-size: 65%;">Tisk: {DATE j.m.Y}</td>
        <td width="50%" style="font-size: 65%;" align="center">Zpracováno systémem Prajet - © 2018 by Zajíc Jan</td>
        <td width="25%" style="text-align: right; font-size: 65%;">{PAGENO}/{nbpg}</td>
    </tr>
</table>');

		$latte = new \Latte\Engine; // nikoliv Nette\Latte\Engine
		$latte->setTempDirectory('../cache');

		$params = [
			'invoice' => $this->fakturyManager->GetFromID($idf),
			'items' => $this->fakturyManager->GetInvoiceItems($idf),
				// ...
		];

		$html = $latte->renderToString(__DIR__ . '/templates/Faktury/dodak_pdf.latte', $params);

		if ($html) {
			$pdf->WriteHTML($html);
		}
		//$pdfFile = $pdf->Output('nazev-souboru.pdf', 'S');
		$pdfFile = $pdf->Output();
	}

	/**
	 * Generate Invoice to PDF
	 * @param type $idf
	 */
	public function actionFakturaPDF($idf) {
		$pdf = new \Mpdf\Mpdf(array('orientation' => 'P', 'format' => 'A4'));

		$pdf->ignore_invalid_utf8 = true;

		$pdf->SetHTMLFooter('
<table width="100%" style="border-top: 0.3mm solid black;">
    <tr>
        <td width="25%" style="font-size: 65%;">Tisk: {DATE j.m.Y}</td>
        <td width="50%" style="font-size: 65%;" align="center">Zpracováno systémem Prajet - © 2018 by Zajíc Jan</td>
        <td width="25%" style="text-align: right; font-size: 65%;">{PAGENO}/{nbpg}</td>
    </tr>
</table>');

		$latte = new \Latte\Engine; // nikoliv Nette\Latte\Engine
		$latte->setTempDirectory('../cache');

		$params = [
			'invoice' => $this->fakturyManager->GetFromID($idf),
			'items' => $this->fakturyManager->GetInvoiceItems($idf),
				// ...
		];

		$html = $latte->renderToString(__DIR__ . '/templates/Faktury/faktura_pdf.latte', $params);

		if ($html) {
			$pdf->WriteHTML($html);
		}
		//$pdfFile = $pdf->Output('nazev-souboru.pdf', 'S');
		$pdfFile = $pdf->Output();
	}

	/**
	 * Ajax component for delete item
	 * @return \Nette\Application\UI\Form
	 */
	public function createComponentItemDeleteForm() {
		$form = new \Nette\Application\UI\Form;
		$form->addHidden('item_id');
		$form->addSubmit('item_delete', 'Smazat')->setHtmlAttribute('class', 'ajax');
		$form->onSuccess[] = [$this, 'signalPolozkadelete'];
		return $form;
	}

	/**
	 * Ajax signal Deleted Item
	 * @param \Nette\Application\UI\Form $form
	 * @param type $values
	 */
	public function signalPolozkadelete(\Nette\Application\UI\Form $form, $values) {
		$this->fakturyManager->DeleteItem($values->item_id);
		if ($this->isAjax()) {
			$this->redrawControl('ItemsList');
		}
	}

	/**
	 * Handle for Item delete
	 * @param type $item_id
	 */
	public function handleItemDelete($item_id) {
		try {
			$this->fakturyManager->DeleteItem($item_id);
			$this->flashMessage("Položka faktury byla smazána.");
		} catch (\Nette\Neon\Exception $e) {
			$this->flashMessage("Položka faktury nebyla smazána! " . $e->getMessage(), 'error');
		}

		if ($this->isAjax()) {
			$this->redrawControl('flash');
			$this->redrawControl('ItemsList');
		}
	}

	/**
	 * Render for Edit item
	 * @param type $idf
	 */
	public function renderEdit($idf) {
		if ($idf < 1) {
			$this->flashMessage("Není zadáno platné ID faktury!");
			$this->redirect('Faktury');
		}

		$this->template->invoice = $invoice = $this->fakturyManager->GetFromID($idf);
		$this->template->items = $this->fakturyManager->GetInvoiceItems($idf);

		//$this->template->item = $idf;


		$this['invoiceForm']->setDefaults($invoice);
		$this['invoiceItemForm']->setDefaults(array('item_faktura' => $idf, 'item_pozice' => 0));
	}

	/**
	 * Create component Invoice
	 * @return \Nette\Application\UI\Form
	 */
	protected function createComponentInvoiceForm() {
		//$this->template->zakaznici = $this->praceManager->getZakaznik();
		//\Tracy\Dumper::dump($this->template->zakaznici);

		$form = new \Nette\Application\UI\Form;
		$form->addHidden('faktura_id');
		//$form->addText('faktura_cislo', 'ID faktury:')->setHtmlAttribute('size', '10');
		$form->addGroup('Hlavička faktury');
		$form->addText('faktura_cislo', 'faktura číslo: ')->setDefaultValue($this->fakturyManager->GenerateNumInvoice($this->year))->setRequired('Zadejte prosím číslo faktury!')->setHtmlAttribute('size', '10');
		$form->addSelect('faktura_status', 'Status: ')->setItems($this->fakturyManager->getSelectStatus())->setHtmlAttribute('size', '1');
		$form->addText('faktura_objednavka', 'objednávka č.: ')->setHtmlAttribute('size', '10');
		$form->addText('faktura_nazev', 'název: ')->setHtmlAttribute('size', '30');
		$form->addText('faktura_datum_vystaveni', 'datum vystavení: ')->setHtmlAttribute('size', '10')->setRequired('Zadejte prosím datum vystavení!');
		$form->addText('faktura_datum_splatnosti', 'datum splatnosti: ')->setHtmlAttribute('size', '10');
		$form->addText('faktura_datum_plneni', 'datum zdan.plnění: ')->setHtmlAttribute('size', '10');
		$form->addText('faktura_datum_uhrazeni', 'Uhrazena dne: ')->setHtmlAttribute('maxlenght', '11')->setHtmlAttribute('size', '15');
		$form->addText('faktura_platba_druh', 'druh platby: ')->setHtmlAttribute('size', '10');
		$form->addText('faktura_fin_uhrazeno', 'uhrazeno zálohou: ')->setHtmlAttribute('size', '10');
		$form->addText('faktura_doprava', 'doprava: ')->setHtmlAttribute('size', '10');
		$form->addGroup('Odběratel:');
		$form->addSelect('faktura_odberatel', 'Odběratel (uložený): ')->setItems($this->fakturyManager->getSelectZakaznik(), true);
		$form->addText('faktura_odb_nazev', 'Název: ')->setHtmlAttribute('size', '50');
		$form->addText('faktura_odb_adresa', 'Adresa: ')->setHtmlAttribute('size', '50');
		$form->addText('faktura_odb_mesto', 'Město: ')->setHtmlAttribute('size', '30');
		$form->addText('faktura_odb_psc', 'PSČ: ')->setHtmlAttribute('size', '6');
		$form->addText('faktura_odb_ic', 'IČ: ')->setHtmlAttribute('size', '15');
		$form->addText('faktura_odb_dic', 'DIČ: ')->setHtmlAttribute('size', '15');

		$form->addGroup('Dodavatel:');
		$form->addText('faktura_dod_nazev', 'Název: ')->setHtmlAttribute('size', '50')->setDefaultValue('Zajíc Jan');
		$form->addText('faktura_dod_adresa', 'Adresa: ')->setHtmlAttribute('size', '50')->setDefaultValue('Americká 2399');
		$form->addText('faktura_dod_mesto', 'Město: ')->setHtmlAttribute('size', '30')->setDefaultValue('Kladno');
		$form->addText('faktura_dod_psc', 'PSČ: ')->setHtmlAttribute('size', '6')->setDefaultValue('27201');
		$form->addText('faktura_dod_ic', 'IČ: ')->setHtmlAttribute('size', '15')->setDefaultValue('67922104');
		$form->addText('faktura_dod_dic', 'DIČ: ')->setHtmlAttribute('size', '15')->setDefaultValue('CZ470906056');
		$form->addText('faktura_dod_tel', 'Tel: ')->setHtmlAttribute('size', '15')->setDefaultValue('+420 602 154 309');
		$form->addText('faktura_dod_email', 'E-mail: ')->setHtmlAttribute('size', '20')->setDefaultValue('delkom@centrum.cz');
		$form->addText('faktura_dod_bank', 'Bankovní účet: ')->setHtmlAttribute('size', '30')->setDefaultValue('135614114 / 0300');
		$form->addText('faktura_dod_registrace')->setHtmlAttribute('size', '70')->setDefaultValue('Podnikatel je zapsán v živnostenském rejstříku. Nejsem plátce DPH.');

		$form->addSubmit('faktura_save', 'Uložit');

		$form->onSuccess[] = [$this, 'InvoiceSucceeded'];

		return $form;
	}

	/**
	 * Create component Invoice Item
	 * @return type
	 */
	protected function createComponentInvoiceItemForm() {
		$form = $this->createComponentInvoiceItemTempForm();
		$form->addSelect('item_select_prace', 'Práce:')->setItems($this->fakturyManager->getSelectWorks());
		$form->addSubmit('item_send', 'Vložit')->setHtmlAttribute('class', 'ajax');
		return $form;
	}

	/**
	 * Create Component Invoice Item edit
	 * @return type
	 */
	protected function createComponentInvoiceItemEditForm() {
		$form = $this->createComponentInvoiceItemTempForm();
		$form->addSubmit('item_send', 'Uložit');
		return $form;
	}

	/**
	 * Create Component Invoice Item Temp
	 * @return \Nette\Application\UI\Form
	 */
	protected function createComponentInvoiceItemTempForm() {
		$form = new \Nette\Application\UI\Form;
		//$form->ajax = true;
		$form->addHidden('item_id');
		$form->addHidden('item_prace');
		$form->addHidden('item_faktura');
		$form->addText('item_katalog', 'Katalogové číslo:');
		$form->addText('item_nazev', 'Název:')->setHtmlAttribute('size', 50);
		$form->addText('item_cena_jedn', 'Cena za jedn.:')->setRequired(true)->addRule(\Nette\Application\UI\Form::FLOAT, 'Cena za jednotku musí být číslo')->addRule(\Nette\Application\UI\Form::RANGE, '%label musí být %d až %d', [0.1, 1000000]);
		$form->addText('item_cena_dph', 'DPH:');
		$form->addText('item_pocet', 'Množství:')->setRequired(true)->addRule(\Nette\Application\UI\Form::FLOAT, 'Počet musí být číslo')->addRule(\Nette\Application\UI\Form::RANGE, '%label musí být %d až %d', [0.1, 1000000]);
		$form->addText('item_jednotka', 'Jednotka:');
		$form->addText('item_poznamka', 'Poznámka:')->setHtmlAttribute('size', 60);
		$form->addText('item_pozice', 'Pozice:')->setRequired(TRUE)->addRule(\Nette\Application\UI\Form::RANGE, 'Rozsah %label musí být od %d do %d (0 je nejvyšší)', [0, 100])->setHtmlType('number')->setHtmlAttribute('size', 5);

		$form->onSuccess[] = [$this, 'InvoiceItemSucceeded'];
		return $form;
	}

	/**
	 * Invoice item succeeded
	 * @param \Nette\Application\UI\Form $form
	 * @param int $values
	 */
	public function InvoiceItemSucceeded(\Nette\Application\UI\Form $form, $values) {
		//$stop();
		if (!key_exists('item_select_prace', $values))
			$values['item_select_prace'] = 0;

		if ($values->item_select_prace > 0) {
			$work = $this->fakturyManager->getWorkFromID($values->item_select_prace);

			$values->item_nazev = $work->prace_projekt . " - " . $work->prace_nazev;
			$values->item_poznamka = $work->prace_popis;
			$values->item_pocet = $work->prace_mnozstvi_predano;
			$values->item_katalog = $work->prace_vykres;
			$values->item_jednotka = "ks";
			$values->item_cena_dph = "0";
			$values->item_cena_jedn = ($work->prace_hodin * $work->prace_sazba) / $work->prace_mnozstvi_predano;
			$values->item_pozice = 0;

			//\Tracy\Dumper::dump($values);
			//$stop();
			if ($this->fakturyManager->AddInvoiceItem($values->item_faktura, $values)) {
				$this->flashMessage("Položka byla uložena.");
			} else
				$this->flashMessage("Položka nebyla uložena!", 'error');
		}
		else {
			//\Tracy\Dumper::dump($values);
			//$stop();
			//$values->item_select_prace = 0;
			$values->item_cena_dph = "0";

			if ($this->fakturyManager->AddInvoiceItem($values->item_faktura, $values)) {
				$this->flashMessage("Položka byla přidána.");
			} else
				$this->flashMessage("Položka nebyla přidána!");
		}

		if ($this->isAjax()) {
			/*
			  $values->item_nazev = "";
			  $values->item_poznamka = "";
			  $values->item_pocet = "";
			  $values->item_katalog = "";
			  $values->item_jednotka = "";
			  $values->item_cena_dph = "";
			  $values->item_cena_jedn = "";
			  $values->item_pozice ++;
			  $this['invoiceItemForm']->setDefaults ( $values );
			 */
			$form->reset();
			$this->redrawControl('ItemsList');
			$this->redrawControl('frmItem');
		} else
			$this->redirect('Faktury:edit', array("idf" => $values->item_faktura));
	}

	/**
	 * Invoice succeeded
	 * @param \Nette\Application\UI\Form $form
	 * @param type $values
	 */
	public function InvoiceSucceeded(\Nette\Application\UI\Form $form, $values) {
		if ($values->faktura_id > 0) {
			if ($this->fakturyManager->Set($values))
				$this->flashMessage("Faktura byla uložena.");
			else
				$this->flashMessage("Faktura nebyla vložena!");

			$this->redirect('Faktury:edit', array("idf" => $values->faktura_id));
		}
		else {
			if ($this->fakturyManager->SetNew($values)) {
				$this->flashMessage("Faktura byla vložena do systému.");
				$this->redirect('Faktury:edit', array("idf" => $values->faktura_id));
			} else {
				$this->flashMessage("Faktura nebyla vložena do systému!");
				//$this->redirect('Faktury:', array("idf" => $values->faktura_id));
			}
		}
	}

}
