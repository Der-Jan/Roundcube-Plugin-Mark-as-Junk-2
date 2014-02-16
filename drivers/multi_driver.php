<?php

/**
 * MarkAsJunk2 run multiple drivers
 * WARNING: Use with extreme caution, running multiple drivers in succession is dangerous!
 * Some drivers destroy the original message and create a new one, altering the message ID ($UID)
 * and so multiple drivers must be run in a specific order. Extra step by step error checking may
 * also be needed to prevent errors from cascading from one driver to another.
 *
 * In this example the edit_headers driver is used to add/remove a [SPAM] tag from the message
 * subject while cmd_learn is used to run sa-learn. Both the edit_headers and cmd_learn driver
 * options must be defined in the MarkAsJunk2 config file and the markasjunk2_learning_driver
 * option set to multi_driver
 *
 * @version 0.1
 * @author Philip Weir
 */

class markasjunk2_multi_driver
{
    public $is_error = false;
    // In this example we want to run the drivers in different orders when making as ham/spam
    // so there is no need to define them here, but if the order wass static we could put something
    // like:
    // private $drivers = array('sa_blacklist', 'cmd_learn');
    private $drivers = array();

    public function spam(&$uids)
    {
        // Define the driver list in the correct order for the mark as spam action
        // We always want the original message to be processed by cmd_learn so when marking as
        // spam cmd_learn should be run first. edit_headers can then alter the message
        $this->drivers = array('sa_blacklist','cmd_learn');
        $this->_call_drivers($uids, true);
    }

    public function ham(&$uids)
    {
        // Define the driver list in the correct order for the mark as ham action
        // We always want the original message to be processed by cmd_learn so when marking as
        // ham edit_headers should be run first, restoring the message to normal then cmd_learn
        // can be run
        $this->drivers = array('sa_blacklist', 'cmd_learn');
        $this->_call_drivers($uids, false);
    }

    private function _call_drivers(&$uids, $spam)
    {
        $rcmail = rcube::get_instance();

        foreach ($this->drivers as $driver) {
            $driver_file = slashify(RCUBE_PLUGINS_DIR) .'/markasjunk2/drivers/'. $driver .'.php';
            $class = 'markasjunk2_' . $driver;

            if (!is_readable($driver_file)) {
                rcube::raise_error(array(
                    'code' => 600,
                    'type' => 'php',
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'message' => "MarkasJunk2 plugin - multi_driver: Unable to open driver file $driver_file"
                    ), true, false);

                $rcmail->output->command('display_message', $rcmail->gettext('internalerror'), 'error');
                $this->is_error = true;
                return;
            }

            include_once $driver_file;

            if (!class_exists($class, false) || !method_exists($class, 'spam') || !method_exists($class, 'ham')) {
                rcube::raise_error(array(
                    'code' => 600,
                    'type' => 'php',
                    'file' => __FILE__,
                    'line' => __LINE__,
                    'message' => "MarkasJunk2 plugin - multi_driver: Broken driver: $driver_file"
                    ), true, false);

                $rcmail->output->command('display_message', $rcmail->gettext('internalerror'), 'error');
                $this->is_error = true;
                return;
            }

            $object = new $class;
            if ($spam)
                $object->spam($uids);
            else
                $object->ham($uids);
        }
    }
}

?>
