<?php
use nw3\app\util\Html;
use nw3\app\util\Time;
use nw3\app\core\Units;
use nw3\app\core\Session;
use nw3\app\helper\Main;
?>

<!--<!DOCTYPE html>
<html lang="en">-->
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN"
	"http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="en" lang="en">
	<head>
		<meta charset='utf-8'>
		<meta name="author" content="Ben Lee-Rodgers" />
		<meta name="description" content="Live and historical weather data and reports from a personal automatic weather station located near Hampstead, North London." />

		<title><?php echo $this->title; ?> - nw3weather</title>

		<link rel="shortcut icon" type="image/x-icon" href="<?php echo ASSET_PATH; ?>favicon.ico" />
		<link rel="stylesheet" type="text/css" href="<?php echo ASSET_PATH; ?>css/global.css" media="screen" title="screen" />

		<script src="<?php echo ASSET_PATH; ?>js/lib/jquery.js"></script>
		<?php if($include_analytics): ?>
			<script src="<?php echo ASSET_PATH; ?>js/analytics.js"></script>
		<?php endif ?>
	</head>

	<body>
		<div id="background">
			<div id="background_header"></div>
			<div id="page">
				<div id="header">
					<table class="legacy" align="center" width="100%" cellpadding="0" cellspacing="0" border="0"><tr>
					<td align="left" valign="top">
						<img src="<?php echo ASSET_PATH; ?>img/leftheadN.JPG" alt="lefthead-anemometer" width="114" height="100" />
					</td>
					<td align="center">
						<a href="<?php echo \Config::HTML_ROOT; ?>" title="Browse to homepage">
							<img src="<?php echo ASSET_PATH; ?>img/newmain.jpg" alt="mainimage_nw3weather" width="698" height="100" />
						</a>
					</td>
					<td align="right" valign="top">
						<img src="<?php echo ASSET_PATH; ?>img/rightheadS.JPG" alt="righthead-weather_box" width="175" height="100" />
					</td>
					</tr></table>
					<div class="subHeader">
						<span id="currms"></span>
					</div>
					<div class="subHeaderR">
						<?php if($show_sneaky_nw3_header): ?>
							<h4 style="display: inline; padding: 0; margin: 0 10em 0 0; color:#565;">
								nw3 weather, Hampstead London England UK
							</h4>
						<?php endif; ?>
						<span style="text-align:right"><?php echo D_date .', '. D_time .' '. D_dst; ?></span>
					</div>
				</div>

				<div id="side-bar" class="leftSideBar">
					<p class="sideBarTitle">Navigation</p>
					<ul>
					<?php
						$sidebar->group('main');
						$sidebar->subheading("Detailed Data", "38610B");
						$sidebar->group('detail');
						$sidebar->subheading("Historical", "0B614B");
						$sidebar->group('historical');
						$sidebar->subheading("Other", "5B9D4B");
						$sidebar->group('other');
					?>
					</ul>
					<p class="sideBarTitle">Site Options</p>
					<table align="center">
						<tr><td><b>Units</b></td></tr>
						<tr><td>
								<form method="get" name="SetUnits" action="">
								<?php foreach (Units::$names as $k => $unit_type): ?>
									<label>
										<input name="unit" type="radio" value="<?php echo $unit_type ?>" onclick="this.form.submit();"
											<?php if(Units::$type === $unit_type): ?>
											   checked="checked"
											<?php endif; ?>
											><?php echo Units::$names_full[$k]; ?>
										</input>
										<br />
								<?php endforeach; ?>
								</form>
						</td></tr>
					</table>
				</div>

				<input id="constants-time" type="hidden" value="<?php echo microtime(true) ?>" />
				<script src="<?php echo ASSET_PATH; ?>js/global.js"></script>

				<div id="main" class="page_<?php echo $this->controller_name ?> subpage_<?php echo $this->page ?>">
					<?php require $this->view; ?>
				</div>
				<div id="main_base"></div>
				<br />
				<div id="footer">
					<div id="footer-links">
						<a href="#header">Top</a> |
						<a href="<?php Html::href('contact'); ?>" title="nw3weather contact page">Contact</a> |
						<a href="http://nw3weather.co.uk" title="Browse to homepage">Home</a>
					</div>
					<div id="copyright">
						&copy; 2010-<?php echo D_year; ?>, BLR<span> | Version 4.0.0</span>
					</div>
					<div id="footer-message">
						Caution: All data is recorded by an amateur-run personal weather station;
						accuracy and reliability <a href="<?php Html::href('about'); ?>#data" title="More about nw3's data">may be poor</a>.
					</div>
					<div id="script_stats">
						<?php $stats = $this->get_stats(); ?>
						Script executed <abbr title="Session Cnt: <?php echo Session::page_count(); ?>">in</abbr> <?php echo $stats['cpu']['time']; ?>
						| DB queries: <?php echo "{$stats['db']['count']} executed in {$stats['db']['time']} ms ({$stats['db']['avg']}, {$stats['db']['prop']}%)"; ?>
						| Memory: <?php echo "{$stats['mem']['current']}  MB ({$stats['mem']['peak']} peak)" ?>
					</div>
					<div id="system_stats">
						Server time: <?php echo date('r', $stats['now']) ?>
						| System time: <?php echo date('r', $stats['data_updated']) ?>
						(Diff: <?php echo Time::secsToReadable($stats['now'] - $stats['data_updated']) ?>)
					</div>
				</div>
			</div>
		</div>
	</body>
</html>
