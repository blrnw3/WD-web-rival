<?php
namespace nw3\config;
/**
 * Description of location
 *
 * @author Ben LR
 */

class Station {
	/** * Latitude, degrees (float) */
	const LAT = 51.556;
	/** * Longitude, degrees (float) */
	const LNG = -0.154;
	/** * Zenith, degrees (float) */
	const ZENITH = 90.2;
	/** * Altitude, meters */
	const ALTITUDE = 55;

	/** * Tip quantity, in mm, of the electronic rain gauge */
	const RAIN_TIP = 0.2;

	/** * IP Address */
	const IP = '217.155.197.157';

	const START_DATE = '2009-02-01';
	const START_DATE_NW3 = '2010-07-18';

}

?>
