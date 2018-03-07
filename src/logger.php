<?php
/**
 * this7 PHP Framework
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @copyright 2016-2018 Yan TianZeng<qinuoyun@qq.com>
 * @license   http://www.opensource.org/licenses/mit-license.php MIT
 * @link      http://www.ub-7.com
 */
namespace this7\logger;
use \Exception;

/**
 * 日志管理
 */
class logger {
    // Singleton instance
    public static $instance = NULL;

    // Path to save log files
    public $_log_path;

    // Level of logging
    public $_threshold = 0;

    // Array of threshold levels to log
    public $_threshold_array = array();

    // File permissions
    public $_file_permissions = 0644;

    // Format of timestamp for log files
    public $_date_fmt = 'Y-m-d H:i:s';

    // Filename prefix
    public $_file_prefix = 'log-';

    // Filename extension
    public $_file_ext = 'log';

    // Whether or not the logger can write to the log files
    public $_enabled = TRUE;

    // Predefined logging levels
    public $_levels = array('ERROR' => 1, 'DEBUG' => 2, 'INFO' => 3, 'ALL' => 4);

    public function test($value = '') {
        # code...
    }

    public function __construct() {
        // do nothing if output log is disabled
        if (!C('logger', 'EnableOutputLog')) {
            $this->_enabled = FALSE;
            return;
        }

        $this->_log_path        = ROOT_DIR . DS . C('logger', 'LogPath');
        $this->_threshold       = C('logger', 'LogThreshold');
        $this->_threshold_array = C('logger', 'LogThresholdArray');

        if (!file_exists($this->_log_path)) {
            mkdir($this->_log_path, 0755, TRUE);
        }
        if (!is_dir($this->_log_path) || !is_writable($this->_log_path)) {
            $this->_enabled = FALSE;
        }

        if (!empty($this->_threshold_array)) {
            $this->_threshold       = 0;
            $this->_threshold_array = array_flip($this->_threshold_array);
        }
    }

    public function __call($name, $arguments) {
        if (in_array($name, array('error', 'debug', 'info'))) {
            return $this->writeLog($name, $arguments);
        }

        throw new Exception("Call to undefined method logger::{$name}()", 1);
    }

    public function getInstance() {
        if (self::$instance === NULL) {
            self::$instance = new static();
        }

        return self::$instance;
    }

    public function writeLog($level, $messages) {
        $result = array();

        for ($i = 0, $size = count($messages); $i < $size; $i += 1) {
            $message = $messages[$i];

            if (is_array($message)) {
                $message = json_encode($message, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            }

            if (is_string($message) || is_numeric($message)) {
                $result[] = $message;
            }
        }

        $instance = $this->getInstance();
        $instance->write_log($level, implode(' ', $result) . "\n");
    }

    // public clone method to prevent cloning of the instance of the *Singleton* instance.
    public function __clone() {}

    // public unserialize method to prevent unserializing of the *Singleton* instance.
    public function __wakeup() {}

    /**
     * Write Log File
     * @param  string $level The error level
     * @param  string $msg   The error message
     * @return bool
     */
    public function write_log($level, $msg) {
        if ($this->_enabled === FALSE) {
            return FALSE;
        }

        $level = strtoupper($level);

        if (TRUE
            && (!isset($this->_levels[$level]) || $this->_levels[$level] > $this->_threshold)
            && !isset($this->_threshold_array[$this->_levels[$level]])
        ) {
            return FALSE;
        }

        $filepath = $this->_log_path . $this->_file_prefix . date('Y-m-d') . '.' . $this->_file_ext;

        $message = '';

        if (!file_exists($filepath)) {
            $newfile = TRUE;
        }

        if (!$fp = @fopen($filepath, 'ab')) {
            return FALSE;
        }

        flock($fp, LOCK_EX);

        // Instantiating DateTime with microseconds appended to initial date
        // is needed for proper support of this format
        if (strpos($this->_date_fmt, 'u') !== FALSE) {
            $microtime_full  = microtime(TRUE);
            $microtime_short = sprintf('%06d', ($microtime_full - floor($microtime_full)) * 1000000);
            $date            = new DateTime(date('Y-m-d H:i:s.' . $microtime_short, $microtime_full));
            $date            = $date->format($this->_date_fmt);

        } else {
            $date = date($this->_date_fmt);
        }

        $message .= $this->_format_line($level, $date, $msg);

        for ($written = 0, $length = strlen($message); $written < $length; $written += $result) {
            if (($result = fwrite($fp, substr($message, $written))) === FALSE) {
                break;
            }
        }

        flock($fp, LOCK_UN);
        fclose($fp);

        if (isset($newfile) && $newfile === TRUE) {
            chmod($filepath, $this->_file_permissions);
        }

        return is_int($result);
    }

    /**
     * Format the log line.
     * @param  string $level   The error level
     * @param  string $date    Formatted date string
     * @param  string $message The log message
     * @return string          Formatted log line with a new line character '\n' at the end
     */
    protected function _format_line($level, $date, $message) {
        return "[{$date}][{$level}] {$message}\n";
    }
}
