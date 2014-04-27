<?php
namespace nw3\app\model;

use nw3\app\core\Db;
use nw3\app\util\Date;
use nw3\app\util\Time;
use nw3\app\util\Maths;

//class Rollingtotal {
//	private $size;
//	private $items = array();
//	private $cnt = 0;
//
//	public $total = 0;
//
//	function __construct($size) {
//		$this->size = $size;
//	}
//
//	function add($value) {
//		$this->items[$cnt] = $value;
//		if($this->cnt > $this->size) {
//
//		}
//		$this->total += $value;
//	}
//}

/**
 * All rain stats n stuff
 */
class Rain extends \Detail {

	const MAX_DRY_QUANTITY = 0.1; // Allow up to (inclusive) this much rain to still count as a dry day

	private $wet_filter;

	function __construct($db) {
		parent::__construct($db, 'rain');

		$this->wet_filter = '> '.self::MAX_DRY_QUANTITY;
	}

	function current_latest() {
		$data = array();

		return $data;
	}

	function spells() {
		$all_spells = $this->all_spells();
		return array(
			self::RECORD => $this->record_spell($all_spells),
			self::NOWYR => $this->longest_spell_nowyr($all_spells),
			self::NOWMON => $this->longest_spell_nowmon($all_spells),
			self::RECORD_M => $this->record_spell_currmon($all_spells),
		);
	}

	function totals() {
		$data = array(
			self::RECORD => array('val' => $this->select('', Db::SUM)),
			self::YESTERDAY => array('val' => $this->filter_date('= '.$this->yest, Db::SUM)),
			self::NOWMON => array('val' => $this->filter_date('>= '.$this->mon_st, Db::SUM)),
			self::NOWYR => array('val' => $this->filter_date('>= '.$this->yr_st, Db::SUM)),
			self::NOWSEAS => array('val' => $this->filter_date('>= '.$this->seas_st, Db::SUM)),
			self::DAY_MON_AGO => array('val' => $this->filter_date('= '.$this->mon_ago, Db::SUM)),
			self::DAY_YR_AGO => array('val' => $this->filter_date('= '.$this->yr_ago, Db::SUM)),
			self::CUM_MON_AGO => array('val' => $this->filter_date(Db::btwn($this->mon_ago_st, $this->mon_ago), Db::SUM)),
			self::CUM_YR_AGO => array('val' => $this->filter_date(Db::btwn($this->yr_ago_st, $this->yr_ago), Db::SUM)),
		);
		$data[self::RECORD]['anom'] = $data[self::RECORD]['val'] / 1; # TODO
		$data[self::NOWMON]['anom'] = $data[self::NOWMON]['val'] / 1; # TODO
		$data[self::NOWYR]['anom'] = $data[self::NOWYR]['val'] / 1; # TODO
		$data[self::CUM_MON_AGO]['anom'] = $data[self::CUM_MON_AGO]['val'] / 1; # TODO
		$data[self::CUM_YR_AGO]['anom'] = $data[self::CUM_YR_AGO]['val'] / 1; # TODO
		# Period tots
		foreach(self::$periods as $period) {
			$data[$period] = $this->total_past_n_days($period);
		}
		return $data;
	}

	function days() {
		$data = array(
			self::RECORD => array('val' => $this->filter_value($this->wet_filter, Db::COUNT)),
			self::NOWMON => array('val' => $this->filter_dt_val('>= '.$this->mon_st, $this->wet_filter, Db::COUNT)),
			self::NOWYR => array('val' => $this->filter_dt_val('>= '.$this->yr_st, $this->wet_filter, Db::COUNT)),
			self::CUM_MON_AGO => array('val' => $this->filter_dt_val(Db::btwn($this->mon_ago_st, $this->mon_ago), $this->wet_filter, Db::COUNT)),
			self::CUM_YR_AGO => array('val' => $this->filter_dt_val(Db::btwn($this->yr_ago_st, $this->yr_ago), $this->wet_filter, Db::COUNT)),
		);
		$data[self::RECORD]['prop'] = $data[self::RECORD]['val'] / $this->num_records;
		$data[self::NOWMON]['prop'] = $data[self::NOWMON]['val'] / D_day;
		$data[self::NOWYR]['prop'] = $data[self::NOWYR]['val'] / (D_doy + 1);
		$data[self::CUM_MON_AGO]['prop'] = $data[self::CUM_MON_AGO]['val'] / D_day;
		$data[self::CUM_YR_AGO]['prop'] = $data[self::CUM_YR_AGO]['val'] / (D_doy + 1);
		# Period tots
		foreach(self::$periods as $period) {
			$data[$period] = $this->count_past_n_days($period);
		}
		return $data;
	}

	function counts() {
		return array(
			self::RECORD => array('val' => $this->num_records)
		);
	}

	/**
	 * Wettest and driest spells (totals over fixed-length periods)
	 * @param type $rainall
	 */
	function extreme_spells() {
		$rainall = $this->select();
		$data = array();
		foreach(self::$periods as $spell_len) {
			$data[$spell_len] = $this->extreme_n_days($spell_len, &$rainall);
		}
		return $data;
	}

	/** Get all wet and dry spells */
	function all_spells() {
		$drylen = $wetlen = 0;
		$dryspells = array();
		$wetspells = array();

		$rainall = $this->select();

		foreach ($rainall as &$db_rain) {
			$rain = $db_rain['rain'];
			$dt = $db_rain['d'];

			if ($rain <= self::MAX_DRY_QUANTITY) {
				$drylen++;
				# End of wet spell
				if($wetlen > 0) {
					$wetspells[] = array('val' => $wetlen, 'dt' => $dt);
					$wetlen = 0;
				}
			} else {
				$wetlen++;
				# End of dry spell
				if($drylen > 0) {
					$dryspells[] = array('val' => $drylen, 'dt' => $dt);
					$drylen = 0;
				}
			}
		}
		# Handle last day (ongoing spell)
		if($drylen > 0) {
			$dryspells[] = array('val' => $drylen, 'dt' => $dt);
		} else {
			$wetspells[] = array('val' => $wetlen, 'dt' => $dt);
		}

		return array(
			'dry' => $dryspells,
			'wet' => $wetspells
		);
	}

	/**
	 * Compute record longest spell
	 */
	function record_spell(&$all_spells) {
		return $this->get_longest_spell_for_period($all_spells, function($spell) {
			return true;
		});
	}

	/**
	 * Compute longest wet and dry spells for all time, for the current month only
	 * A spell counts if its midpoint falls within the current month
	 */
	function record_spell_currmon(&$all_spells) {
		return $this->get_longest_spell_for_period($all_spells, function($spell) {
			return date('n', $spell['dt'] - $spell['val'] * Date::secs_DAY / 2) == D_month;
		});
	}

	/** Longest wet and dry spells for this year */
	function longest_spell_nowyr(&$all_spells) {
		$this->get_longest_spell_for_period($all_spells, function($spell) {
			return date('Y', $spell['dt'] - $spell['val'] * Date::secs_DAY / 2) == D_year;
		});
	}
	/** Longest wet and dry spells for this month */
	function longest_spell_nowmon(&$all_spells) {
		$this->get_longest_spell_for_period($all_spells, function($spell) {
			return date('Yn', $spell['dt'] - $spell['val'] * Date::secs_DAY / 2) == ((string)D_year + (string)D_month);
		});
	}

	private function total_past_n_days($n) {
		return array(
			'val' => $this->filter_date('> '.Date::mkday(D_day-$n), Db::SUM),
			'anom' => 'TODO'
		);
	}
	private function count_past_n_days($n) {
		$cnt = $this->filter_dt_val('> '.Date::mkday(D_day-$n), $this->wet_filter, Db::COUNT);
		return array(
			'val' => $cnt,
			'prop' => $cnt / $n
		);
	}

	private function extreme_n_days($n, &$rainall) {
		$driest = INT_MAX;
		$wettest = 0;

		$i = 0;
		foreach ($rainall as $dt => $rain) {
			$cumrn += $rain;

			if ($i >= $n) {
				$cumrn -= $rainall[$dt - $n * Date::secs_DAY];
				if ($cumrn < $driest) {
					$driest = $cumrn;
					$driest_end = $dt;
				}
			}
			# Wet spells don't require accumulation of [n] days
			if ($cumrn > $wettest) {
				$wettest = $cumrn;
				$wettest_end = $dt;
			}
			$i++;
		}
		return array(
			'dry' => array('val' => $driest, 'dt' => $driest_end),
			'wet' => array('val' => $wettest, 'dt' => $wettest_end)
		);
	}

	private function get_longest_spell_for_period($all_spells, $fn_is_in_period) {
		$max = 0;
		foreach ($all_spells as $spell) {
			if ($spell['val'] > $max && $fn_is_in_period($spell)) {
				$max = $spell['val'];
				$longest = $spell;
			}
		}
		return $longest;
	}
}

?>
