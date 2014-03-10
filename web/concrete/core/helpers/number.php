<?
defined('C5_EXECUTE') or die("Access Denied.");
class Concrete5_Helper_Number {

	public function flexround($value) {
		$v = explode('.', $value);
		$p = 0;
		for ($i = 0; $i < strlen($v[1]); $i++) {
			if (substr($v[1], $i, 1) > 0) {
				$p = $i+1;
			}
		}
		return round($value, $p);
	}
	* @example http://www.concrete5.org/documentation/how-tos/developers/formatting-numbers/ See the Formatting numbers how-to for more details
	* @example http://www.concrete5.org/documentation/how-tos/developers/formatting-numbers/ See the Formatting numbers how-to for more details
	* @example http://www.concrete5.org/documentation/how-tos/developers/formatting-numbers/ See the Formatting numbers how-to for more details
}

?>
	* @example http://www.concrete5.org/documentation/how-tos/developers/formatting-numbers/ See the Formatting numbers how-to for more details
	/** Formats a size (measured in bytes, KB, MB, ...).
	* @param number $size The size to be formatted, in bytes.
	* @param string $forceUnit = '' Set to 'bytes', 'KB', 'MB', 'GB' or 'TB' if you want to force the unit, leave empty to automatically determine the unit.
	* @return string|mixed If $size is not numeric, the function returns $size (untouched), otherwise it returns the size with the correct usits (GB, MB, ...) and formatted following the locale rules.
	* @example formatSize(0) returns '0 bytes'
	* @example formatSize(1) returns '1 byte'
	* @example formatSize(1000) returns '1,000 bytes'
	* @example formatSize(1024) returns '1.00 KB'
	* @example formatSize(1024, 'bytes') returns '1024 bytes'
	* @example formatSize(1024, 'GB') returns '0.00 GB'
	* @example formatSize(2000000) returns '1.91 MB'
	* @example formatSize(-5000) returns '-4.88 KB'
	* @example formatSize('hello') returns 'hello'
	*/
	public function formatSize($size, $forceUnit = '') {
		if(!is_numeric($size)) {
			return $size;
		}
		if(strlen($forceUnit) && array_search($forceUnit, array('bytes', 'KB', 'MB', 'GB', 'TB')) === false) {
			$forceUnit = '';
		}
		if($forceUnit === 'bytes' || (abs($size) < 1024 && (!strlen($forceUnit)))) {
			return t2(/*i18n %s is a number */'%s byte', '%s bytes', $size, $this->format($size, 0));
		}
		$size /= 1024;
		if($forceUnit === 'KB' || (abs($size) < 1024 && (!strlen($forceUnit)))) {
			return t(/*i18n %s is a number, KB means Kilobyte */'%s KB', $this->format($size, 2));
		}
		$size /= 1024;
		if($forceUnit === 'MB' || (abs($size) < 1024 && (!strlen($forceUnit)))) {
			return t(/*i18n %s is a number, MB means Megabyte */'%s MB', $this->format($size, 2));
		}
		$size /= 1024;
		if($forceUnit === 'GB' || (abs($size) < 1024 && (!strlen($forceUnit)))) {
			return t(/*i18n %s is a number, GB means Gigabyte */'%s GB', $this->format($size, 2));
		}
		return t(/*i18n %s is a number, TB means Terabyte */'%s TB', $this->format($size, 2));
	}