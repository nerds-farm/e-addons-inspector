<?php
namespace EAddonsInspector\Modules\Inspector;

use EAddonsForElementor\Core\Utils;
use EAddonsForElementor\Base\Module_Base;

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Hide extenstion
 *
 * @since 1.0.1
 */
class Inspector extends Module_Base {

    public function __construct() {
            parent::__construct();
            add_filter('e_addons/current_element', [$this, 'filter_current_element']);
    }
    
    public function get_name() {
            return 'inspector';
    }
    
    public function filter_current_element($args) {
        return \EAddonsInspector\Modules\Inspector\Globals\Frontend_Inspector::get_current_element();
    }

}
