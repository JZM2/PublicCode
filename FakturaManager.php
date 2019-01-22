<?php
namespace App\Model;

use Nette;

/**
 * Faktura management.
 */
class FakturaManager {

	use Nette\SmartObject;

	const
			TABLE_NAME = 'faktury',
			COLUMN_ID = 'faktura_id',
			COLUMN_CISLO = 'faktura_cislo',
			COLUMN_NAZEV = 'faktura_nazev',
			COLUMN_DATUM_VYSTAVENI = 'faktura_datum_vystaveni',
			COLUMN_DATUM_SPLATNOST = 'faktura_datum_splatnosti',
			COLUMN_DATUM_PLNENI = 'faktura_datum_plneni',
			COLUMN_DOPRAVA = 'faktura_doprava',
			COLUMN_CENA = 'faktura_cena',
			COLUMN_PLATBA_DRUH = 'faktura_platba_druh',
			COLUMN_PLATBA_ZALOHA = 'faktura_fin_uhrazeno',
			COLUMN_STATUS = 'faktura_status',
			COLUMN_OBJEDNAVKA = 'faktura_objednavka',
			COLUMN_UHRAZENO = 'faktura_datum_uhrazeni',
			COLUMN_ZAKAZNIK = 'faktura_odberatel';

	/** @var Nette\Database\Connection */
	private $database;

	/**
	 * 
	 * @param Nette\Database\Connection $database
	 */
	public function __construct(Nette\Database\Connection $database) {
		$this->database = $database;
	}

	/**
	 * 
	 * @param type $rok
	 * @return type
	 */
	public function Get($rok = 0) {
		if ($rok > 0)
			$faktury = $this->database->fetchAll("SELECT * FROM faktury WHERE YEAR(faktura_datum_vystaveni) = ? ", $rok);
		else
			$faktury = $this->database->fetchAll("SELECT * FROM faktury");

		return $faktury;
	}

	/**
	 * 
	 * @param type $id
	 * @return type
	 */
	public function GetFromID($id) {
		$faktura = $this->database->fetch("SELECT * FROM faktury WHERE faktura_id = ?", $id);

		if ($faktura['faktura_datum_uhrazeni'] < '1990-12-31')
			$faktura['faktura_datum_uhrazeni'] = '';

		$faktura["faktura_datum_vystaveni"] = strftime("%d.%m.%Y", strtotime($faktura['faktura_datum_vystaveni']));
		$faktura["faktura_datum_splatnosti"] = strftime("%d.%m.%Y", strtotime($faktura['faktura_datum_splatnosti']));
		$faktura["faktura_datum_plneni"] = strftime("%d.%m.%Y", strtotime($faktura['faktura_datum_plneni']));
		$faktura["faktura_datum_uhrazeni"] = strftime("%d.%m.%Y", strtotime($faktura['faktura_datum_uhrazeni']));

		return $faktura;
	}

	/**
	 * 
	 * @return type
	 */
	public function getSelectStatus() {
		$tmp_statusy = $this->database->fetchAll("SELECT * FROM faktury_status");
		foreach ($tmp_statusy as $status) {
			$statusy[$status->status_id] = $status->status_nazev;
		}
		return $statusy;
	}

	/**
	 * 
	 * @param type $year
	 * @return string
	 */
	public function GenerateNumInvoice($year) {
		$invoice_number = Date("Y") . \Nette\Utils\Strings::padLeft($this->getSumInvoiceInYear($year) + 1, 3, '0'); // '+++Nette'
		return $invoice_number;
	}

	/**
	 * 
	 * @param type $year
	 * @return type
	 */
	private function getSumInvoiceInYear($year) {
		$res = $this->database->fetch("SELECT Count(faktura_id) AS faktura_pocet FROM faktury WHERE YEAR(faktura_datum_vystaveni) = ?", $year);
		return $res->faktura_pocet;
	}

	/**
	 * 
	 * @param type $values
	 * @return int
	 */
	public function SetNew($values) {
		//$stop();
		try {
			if ($values->faktura_datum_uhrazeni == '')
				$values->faktura_datum_uhrazeni = '0000-00-00';

			if ($values->faktura_fin_uhrazeno == '')
				$values->faktura_fin_uhrazeno = 0;

			$values->faktura_datum_vystaveni = PraceManager::getUnixDate($values->faktura_datum_vystaveni);
			$values->faktura_datum_splatnosti = PraceManager::getUnixDate($values->faktura_datum_splatnosti);
			$values->faktura_datum_plneni = PraceManager::getUnixDate($values->faktura_datum_plneni);
			$values->faktura_datum_uhrazeni = PraceManager::getUnixDate($values->faktura_datum_uhrazeni);

			if ($values->faktura_odberatel > 0) {
				$partner = $this->getZakaznik($values->faktura_odberatel);

				$values->faktura_odb_nazev = $partner->subjekt_nazev;
				$values->faktura_odb_adresa = $partner->subjekt_adresa;
				$values->faktura_odb_mesto = $partner->subjekt_mesto;
				$values->faktura_odb_psc = $partner->subjekt_psc;
				$values->faktura_odb_ic = $partner->subjekt_id;
				$values->faktura_odb_dic = $partner->subjekt_dic;
			}

			$this->database->query('INSERT INTO faktury ?', [ // tady můžeme otazník vynechat
				self::COLUMN_CISLO => $values->faktura_cislo,
				self::COLUMN_NAZEV => $values->faktura_nazev,
				self::COLUMN_DATUM_VYSTAVENI => $values->faktura_datum_vystaveni,
				self::COLUMN_DATUM_SPLATNOST => $values->faktura_datum_splatnosti,
				self::COLUMN_DATUM_PLNENI => $values->faktura_datum_plneni,
				self::COLUMN_DOPRAVA => $values->faktura_doprava,
				self::COLUMN_PLATBA_DRUH => $values->faktura_platba_druh,
				self::COLUMN_PLATBA_ZALOHA => $values->faktura_fin_uhrazeno,
				self::COLUMN_ZAKAZNIK => 0 /* $values->faktura_odberatel */,
				self::COLUMN_OBJEDNAVKA => $values->faktura_objednavka,
				self::COLUMN_STATUS => $values->faktura_status,
				self::COLUMN_UHRAZENO => $values->faktura_datum_uhrazeni,
				'faktura_dod_nazev' => $values->faktura_dod_nazev,
				'faktura_dod_adresa' => $values->faktura_dod_adresa,
				'faktura_dod_psc' => $values->faktura_dod_psc,
				'faktura_dod_mesto' => $values->faktura_dod_mesto,
				'faktura_dod_ic' => $values->faktura_dod_ic,
				'faktura_dod_dic' => $values->faktura_dod_dic,
				'faktura_dod_tel' => $values->faktura_dod_tel,
				'faktura_dod_email' => $values->faktura_dod_email,
				'faktura_dod_registrace' => $values->faktura_dod_registrace,
				'faktura_dod_bank' => $values->faktura_dod_bank,
				'faktura_odb_nazev' => $values->faktura_odb_nazev,
				'faktura_odb_adresa' => $values->faktura_odb_adresa,
				'faktura_odb_mesto' => $values->faktura_odb_mesto,
				'faktura_odb_psc' => $values->faktura_odb_psc,
				'faktura_odb_ic' => $values->faktura_odb_ic,
				'faktura_odb_dic' => $values->faktura_odb_dic,
			]);
			return 1;
		} catch (Nette\Neon\Exception $e) {
			\Tracy\Dumper::dump($values);
			return 0;
		}
	}

	public function Set($values) {
		try {

			if ($values->faktura_datum_uhrazeni == '')
				$values->faktura_datum_uhrazeni = NULL;

			$values->faktura_datum_vystaveni = PraceManager::getUnixDate($values->faktura_datum_vystaveni);
			$values->faktura_datum_splatnosti = PraceManager::getUnixDate($values->faktura_datum_splatnosti);
			$values->faktura_datum_plneni = PraceManager::getUnixDate($values->faktura_datum_plneni);
			$values->faktura_datum_uhrazeni = PraceManager::getUnixDate($values->faktura_datum_uhrazeni);

			if ($values->faktura_fin_uhrazeno == "")
				$values->faktura_fin_uhrazeno = 0.0;


			$this->database->query('UPDATE faktury SET', [ // tady můžeme otazník vynechat
				self::COLUMN_CISLO => $values->faktura_cislo,
				self::COLUMN_NAZEV => $values->faktura_nazev,
				self::COLUMN_DATUM_VYSTAVENI => $values->faktura_datum_vystaveni,
				self::COLUMN_DATUM_SPLATNOST => $values->faktura_datum_splatnosti,
				self::COLUMN_DATUM_PLNENI => $values->faktura_datum_plneni,
				self::COLUMN_DOPRAVA => $values->faktura_doprava,
				self::COLUMN_PLATBA_DRUH => $values->faktura_platba_druh,
				self::COLUMN_PLATBA_ZALOHA => $values->faktura_fin_uhrazeno,
				//self::COLUMN_ZAKAZNIK => $values->faktura_odberatel,
				self::COLUMN_OBJEDNAVKA => $values->faktura_objednavka,
				self::COLUMN_STATUS => $values->faktura_status,
				self::COLUMN_UHRAZENO => $values->faktura_datum_uhrazeni,
				'faktura_dod_nazev' => $values->faktura_dod_nazev,
				'faktura_dod_adresa' => $values->faktura_dod_adresa,
				'faktura_dod_psc' => $values->faktura_dod_psc,
				'faktura_dod_mesto' => $values->faktura_dod_mesto,
				'faktura_dod_ic' => $values->faktura_dod_ic,
				'faktura_dod_dic' => $values->faktura_dod_dic,
				'faktura_dod_tel' => $values->faktura_dod_tel,
				'faktura_dod_email' => $values->faktura_dod_email,
				'faktura_dod_registrace' => $values->faktura_dod_registrace,
				'faktura_dod_bank' => $values->faktura_dod_bank,
				'faktura_odb_nazev' => $values->faktura_odb_nazev,
				'faktura_odb_adresa' => $values->faktura_odb_adresa,
				'faktura_odb_mesto' => $values->faktura_odb_mesto,
				'faktura_odb_psc' => $values->faktura_odb_psc,
				'faktura_odb_ic' => $values->faktura_odb_ic,
				'faktura_odb_dic' => $values->faktura_odb_dic,
					], 'WHERE faktura_id = ?', $values->faktura_id);
			return 1;
		} catch (Nette\Neon\Exception $e) {
			//\Tracy\Dumper::dump($values);
			return 0;
		}
	}

	/**
	 * 
	 * @param integer $zakaznik_id
	 * @return array
	 */
	public function getZakaznik($zakaznik_id) {
		$zakaznik = $this->database->fetch("SELECT * FROM subjekty WHERE subjekt_id = ?", $zakaznik_id);
		return $zakaznik;
	}

	/**
	 * 
	 * @return string
	 */
	public function getSelectZakaznik() {
		$tmp_zakaznici = $this->database->fetchPairs("SELECT subjekt_id, concat(subjekt_nazev, '(', subjekt_id, ') ', status_name ) AS subjekt_nazev FROM subjekty, subjekt_status WHERE status_id = subjekt_status");

		$zakaznici = array(0 => 'nezadáno', 'Odběratelé' => $tmp_zakaznici);

		return $zakaznici;
	}

	/**
	 * 
	 * @return string
	 */
	public function getSelectWorks() {
		$tmp_works = $this->database->fetchPairs("SELECT prace_id, concat(prace_objednavka_cislo, ' ', prace_nazev) AS prace_nazev FROM prace WHERE prace_status < 3");

		$works = array(0 => 'nezadáno', $tmp_works);
		return $works;
	}

	/**
	 * 
	 * @param type $idw
	 * @return type
	 */
	public function getWorkFromID($idw) {
		$work = $this->database->fetch("SELECT * FROM prace WHERE prace_id = ?", $idw);

		return $work;
	}

	/**
	 * 
	 * @param type $invoice_id
	 * @return type
	 */
	public function GetInvoiceItems($invoice_id) {
		$items = $this->database->fetchAll("SELECT * FROM faktura_polozky WHERE item_faktura = ?", $invoice_id);
		return $items;
	}

	/**
	 * 
	 * @param type $item_id
	 * @return type
	 */
	public function GetInvoiceItemFromID($item_id) {
		$item = $this->database->fetch("SELECT * FROM faktura_polozky WHERE item_id = ?", $item_id);
		return $item;
	}

	/**
	 * 
	 * @param type $invoice_id
	 * @param type $values
	 * @return int
	 */
	public function AddInvoiceItem($invoice_id, $values) {
		if ($values->item_id < 1) {
			$this->database->query('INSERT INTO faktura_polozky ?', [ // tady můžeme otazník vynechat
				'item_faktura' => $invoice_id,
				'item_prace' => $values->item_select_prace,
				'item_katalog' => $values->item_katalog,
				'item_nazev' => $values->item_nazev,
				'item_cena_jedn' => $values->item_cena_jedn,
				'item_cena_dph' => $values->item_cena_dph,
				'item_pocet' => $values->item_pocet,
				'item_jednotka' => $values->item_jednotka,
				'item_poznamka' => $values->item_poznamka,
				'item_pozice' => $values->item_pozice
			]);

			$prace = new \App\Model\PraceManager($this->database);
			$prace->setInInvoice($values->item_select_prace);
			return 1;
		} else {
			$this->database->query('UPDATE faktura_polozky SET', [ // tady můžeme otazník vynechat
				'item_katalog' => $values->item_katalog,
				'item_nazev' => $values->item_nazev,
				'item_cena_jedn' => $values->item_cena_jedn,
				'item_cena_dph' => $values->item_cena_dph,
				'item_pocet' => $values->item_pocet,
				'item_jednotka' => $values->item_jednotka,
				'item_poznamka' => $values->item_poznamka,
				'item_pozice' => $values->item_pozice
					], 'WHERE item_id = ?', $values->item_id);
			return 1;
		}
	}

	/**
	 * 
	 * @param type $id_item
	 * @return type
	 */
	public function DeleteItem($id_item) {
		return $this->database->query("DELETE FROM faktura_polozky WHERE item_id = ?", $id_item);
	}

	/**
	 * 
	 * @param type $datum
	 * @return type
	 */
	public static function getUnixDate($datum) {
		$date = Date("Y-m-d", strtotime($datum));
		return $date;
	}

}
