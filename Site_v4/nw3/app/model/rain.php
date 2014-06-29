<?php
namespace nw3\app\model;

use nw3\app\core\Db;
use nw3\app\util\Date;
use nw3\app\util\Time;
use nw3\app\util\Maths;

//class Rollingtotal {
//	private $size;
//	private $items = [];
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
class Rain extends Detail {

	const AGG = 'total';

	const MAX_DRY_QUANTITY = 0.1; // Allow up to (inclusive) this much rain to still count as a dry day

	private $wet_filter;

	function __construct() {
		parent::__construct('rain');

		$this->wet_filter = '> '.self::MAX_DRY_QUANTITY;
	}

	public function spells() {
		$all_spells = $this->all_spells();
		$data = ['dry' => [], 'wet' => []];
		foreach (self::get_periods('has_spell') as $period) {
			$data['dry'][$period] = $this->get_longest_spell_for_period($all_spells['dry'], $this->get_spell_filter($period));
			$data['wet'][$period] = $this->get_longest_spell_for_period($all_spells['wet'], $this->get_spell_filter($period));
		}
		return $data;
	}

	/**
	 * If rained today, the number of days it has rained consecutively,
	 * else the number of complete days since it last rained
	 */
	public function curr_spell($rained_today) {
		$cond = $rained_today ? '=' : '>';
		$dt = date(Db::DATE_FORMAT, D_now);
		return $this->db->query("DATEDIFF('$dt', d)")
			->filter("rain $cond 0")
			->extreme(Db::MAX, 'd')
			->scalar()
		;
	}

	public function totals() {
		$data = [];
		foreach (self::get_periods('multi') as $period) {
			$data[$period] = [
				'val' => $this->period_agg($period),
				'anom' => $this->period_sum_anom($period)
			];
		}
		$data[self::NOWMON]['anom_f'] = $this->get_period_end_anom($data, self::NOWMON);
		$data[self::NOWYR]['anom_f'] = $this->get_period_end_anom($data, self::NOWYR);
		$data[self::NOWSEAS]['anom_f'] = $this->get_period_end_anom($data, self::NOWSEAS);
		return $data;
	}

	public function days() {
		$data = [];
		foreach (self::get_periods('multi') as $period) {
			$cnt = $this->period_count($period, $this->wet_filter);
			$data[$period] = [
				'val' => $cnt,
				'prop' => $cnt / $this->period_lengths[$period]
			];
		}
		return $data;
	}

	public function extreme_days_monthly() {
		$data = ['max' => [], 'min' => []];
		foreach (self::get_periods('month_recs') as $period) {
			$cnt_max = $this->period_count_month_extreme($period, Db::MAX, $this->wet_filter);
			$cnt_min = $this->period_count_month_extreme($period, Db::MIN, $this->wet_filter);
			$data['max'][$period] = ['val' => $cnt_max['val'], 'dt' => $cnt_max['d']];
			$data['min'][$period] = ['val' => $cnt_min['val'], 'dt' => $cnt_min['d']];
		}
		return $data;
	}

	/**
	 * Wettest and driest spells (totals over fixed-length periods)
	 * @param type $rainall
	 */
	public function extreme_spells() {
		$rainall = $this->select();
		$data = [];
		foreach(self::$periods as $spell_len) {
			$data[$spell_len] = $this->extreme_n_days($spell_len, $rainall);
		}
		return $data;
	}

	/** Get all wet and dry spells */
	function all_spells() {
		$drylen = $wetlen = 0;
		$dryspells = [];
		$wetspells = [];

		$rainall = $this->db->query($this->colname, Db::timestamp('d'))->all();

		foreach ($rainall as &$db_rain) {
			$rain = $db_rain['rain'];
			$dt = (int)$db_rain['dt'];

			if ($rain <= self::MAX_DRY_QUANTITY) {
				$drylen++;
				# End of wet spell
				if($wetlen > 0) {
					$wetspells[] = ['val' => $wetlen, 'dt' => $dt];
					$wetlen = 0;
				}
			} else {
				$wetlen++;
				# End of dry spell
				if($drylen > 0) {
					$dryspells[] = ['val' => $drylen, 'dt' => $dt];
					$drylen = 0;
				}
			}
		}
		# Handle last day (ongoing spell)
		if($drylen > 0) {
			$dryspells[] = ['val' => $drylen, 'dt' => $dt];
		} else {
			$wetspells[] = ['val' => $wetlen, 'dt' => $dt];
		}

		return [
			'dry' => $dryspells,
			'wet' => $wetspells
		];
	}

	/**
	 * Filter function for longest wet and dry spells for given period
	 * A spell counts if its midpoint falls within the period
	 */
	private function get_spell_filter($period) {
		if(in_array($period, self::$periodsn)) {
			return function($spell) use (&$period) {
				return ($spell['dt'] - $spell['val'] * Date::secs_DAY / 2) > Date::mkday(D_day - $period);
			};
		}
		# Named period
		switch($period) {
			case self::RECORD:
				return function($spell) {
					return true;
				};
			case self::NOWMON:
				return function($spell) {
					return $spell['dt'] - $spell['val'] * Date::secs_DAY / 2 > Date::mkday(1);
				};
			case self::NOWYR:
				return function($spell) {
					return $spell['dt'] - $spell['val'] * Date::secs_DAY / 2 > Date::mkdate(1, 1);
				};
			case self::NOWSEAS:
				return function($spell) {
					return $spell['dt'] - $spell['val'] * Date::secs_DAY / 2 > Date::mkdate(Date::get_current_season_start_month(), 1);
				};
			case self::RECORD_M:
				return function($spell) {
					return date('Yn', $spell['dt'] - $spell['val'] * Date::secs_DAY / 2) == (D_year .''. D_month);
				};
		}
		throw new \Exception("Invalid period '$period' specified");
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
		return [
			'dry' => ['val' => $driest, 'dt' => $driest_end],
			'wet' => ['val' => $wettest, 'dt' => $wettest_end]
		];
	}

	private function get_longest_spell_for_period(&$all_spells, $fn_is_in_period) {
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
