<?php

namespace EAddonsInspector\Modules\Inspector\Globals;

use EAddonsForElementor\Core\Utils;
use EAddonsForElementor\Base\Base_Global;
use Elementor\Controls_Manager;

if (!defined('ABSPATH'))
    exit; // Exit if accessed directly

/**
 * Debug extenstion
 *
 * @since 1.0.1
 */
class Frontend_Inspector extends Base_Global {

    const GET_VAR = 'e-inspector';

    public static $elements = [];
    public static $elements_time = [];
    public static $elements_hidden = [];
    public static $elements_categories = [];
    public static $elements_settings = [];
    public static $elementor_data = [];
    public static $elementor_current = false;
    public static $elementor_data_current = '';
    public static $elementor_capability = false;

    public function __construct() {
        parent::__construct();
        $this->add_actions();
    }
    
    public function get_pid() {
        return 304;
    }
    
    public function get_icon() {
        return 'eadd-frontend-inspector';
    }

    public function get_name() {
        return 'frontend-inspector';
    }

    public function add_actions() {
        
        add_action('elementor/editor/after_enqueue_scripts', [$this, 'enqueue_editor_assets']);
        
        //if (!\Elementor\Plugin::$instance->editor->is_edit_mode()) {
        if (!is_admin()) {
            if (!Utils::is_preview()) {
                self::$elementor_capability = Utils::get_elementor_capability();
                if (self::$elementor_capability || (isset($_GET[self::GET_VAR]))) {
                    // elements report
                    add_action("elementor/frontend/widget/before_render", array($this, 'start_element'), 11, 2);
                    add_action("elementor/frontend/builder_content_data", array($this, 'start_element'), 11, 2); // template start
                    add_action("elementor/frontend/the_content", array($this, 'end_element'), 11, 2); // template end
                    add_action("elementor/frontend/section/before_render", array($this, 'start_element'), 11, 2);
                    add_action("elementor/frontend/column/before_render", array($this, 'start_element'), 11, 2);
                    add_action("elementor/frontend/section/after_render", array($this, 'end_element'), 11, 2);
                    add_action("elementor/frontend/column/after_render", array($this, 'end_element'), 11, 2);
                    add_action("elementor/frontend/widget/after_render", array($this, 'end_element'), 11, 2);
                    add_action('admin_bar_menu', array($this, 'add_elementor_navigator'), 100, 2);
                    add_action('wp_footer', [$this, 'print_frontend_inspector_submenu']);
                    if (isset($_GET[self::GET_VAR])) {
                        add_action('wp_head', function() {
                            echo '<meta name="robots" content="noindex" />';
                        });
                        add_action('wp_footer', [$this, 'print_frontend_inspector']);
                    }
                }
            }
        }
    }

    /**
     * Enqueue admin styles
     *
     * @since 0.7.0
     *
     * @access public
     */
    public function enqueue_editor_assets() {
        wp_enqueue_script('e-addons-editor-quick');
    }

    public function start_element($element = false, $template_id = 0) {
        $id = 0;

        if (is_object($element)) {
            $type = $element->get_type();
            $name = $element->get_name();
            $id = $element->get_id();
            self::$elements_settings[$id] = $element->get_settings();

            self::$elementor_current = $element;
        }

        if (self::$elementor_capability || isset($_GET[self::GET_VAR])) {
            if (is_string($element) || is_array($element) || $template_id) {
                if (!$template_id) {
                    if (is_string($element)) {
                        $template_id = Utils::get_template_from_html($element);
                    }
                    if (!$template_id && is_array($element)) {
                        //echo '<pre>';var_dump($element);echo '</pre>'; die();
                        //$template_id = $this->get_last_template_id();
                        $template_id = get_the_ID();
                        $template_id = Utils::get_post_id_by_data($element, $template_id);
                        //var_dump($template_id);
                        self::$elements_settings[$template_id] = $element;
                    }
                }
                $template = get_post($template_id);
                if ($template) {
                    $type = 'template';
                    $name = $template->post_name;
                    $template_id = $id = $template->ID;

                    self::$elementor_current = $element;
                }
            }
        }

        if ($id) {
            if (isset(self::$elements[$type][$name][$id])) {
                self::$elements[$type][$name][$id]++;
            } else {
                self::$elements[$type][$name][$id] = 1;
            }
        }

        if ($id) {
            if (!empty(self::$elementor_data_current)) {
                self::$elementor_data_current .= ' > ';
            }
            self::$elementor_data_current .= $type . '-' . $id;
            self::$elementor_data[self::$elementor_data_current] = $name;

            self::$elements_time[$id]['start'] = microtime(true);
        }



        //var_dump($template_id);
        //if ($template_id) {
        return $element;
        //}
    }

    public function end_element($element = false, $template_id = 0) {

        $id = 0;

        if (is_object($element)) {
            $type = $element->get_type();
            $name = $element->get_name();
            $id = $element->get_id();
        }

        if (is_string($element) || is_array($element) || $template_id) {
            if (!$template_id && !is_array($element)) {
                $template_id = Utils::get_template_from_html($element);
            }
            $template = get_post($template_id);
            if ($template) {
                $type = 'template';
                $name = $template->post_name;
                $template_id = $id = $template->ID;
            }
        }

        if ($id) {
            $elements = explode(' > ', self::$elementor_data_current);
            array_pop($elements);
            self::$elementor_data_current = implode(' > ', $elements);

            self::$elements_time[$id]['end'] = microtime(true);
        }

        return $element;
    }

    public function get_last_template_id() {
        if (!empty(self::$elementor_data_current)) {
            $pieces = explode(' > ', self::$elementor_data_current);
            $pieces = array_reverse($pieces);
            foreach ($pieces as $key => $value) {
                list($type, $id) = explode('-', $value);
                if ($type == 'template') {
                    return $id;
                }
            }
        }
        return false;
    }

    public function add_elementor_navigator($admin_bar) {
        global $wp;
        $href = home_url(add_query_arg(array(self::GET_VAR => ''), $wp->request));
        $admin_bar->add_menu(array(
            'id' => 'e-addons-inspector',
            'title' => '<i class="eadd-frontend-inspector" style="font-family: eadd; -webkit-text-stroke: 1px #FFF;"></i> Inspector',
            'href' => $href,
            'meta' => array(
                'title' => __('Elementor Frontend Inspector'),
                'class' => 'menupop',
            ),
        ));

        wp_enqueue_style('e-addons-icons', $this->get_plugin_url().'assets/css/e-addons-icons.css');
        wp_enqueue_style('e-addons-admin-bar');
        wp_enqueue_script('e-addons-admin-bar');
        wp_enqueue_style('e-addons-inspector');
        wp_enqueue_script('e-addons-inspector');
    }

    public function print_frontend_inspector_submenu() {
        if (self::$elementor_capability) {
            $this->parse_elementor_data();
            $template_ids = $this->get_elementor_submenu_ids(self::$elementor_data);
            if (!empty($template_ids)) {
                ?>
                <div id="wp-admin-bar-e-addons-edit-template-wrapper" class="ab-sub-wrapper" style="visibility: hidden;">
                    <ul id="wp-admin-bar-e-addons-edit-template" class="ab-submenu">
                        <?php $this->print_elementor_submenu($template_ids); ?>
                    </ul>
                </div>
                <?php
            } else {
                ?><style>#wp-admin-bar-e-addons-inspector{display: none;}</style><?php
            }
        }
    }

    public function get_elementor_submenu_ids($elementor_data, $templates = array()) {
        if (!empty($elementor_data)) {
            if (is_array($elementor_data)) {
                foreach ($elementor_data as $ekey => $evalue) {
                    list($type, $id) = explode('-', $ekey);
                    if ($type == 'template') {
                        $templates[$id] = intval($id);
                    }
                    $templates = $this->get_elementor_submenu_ids($evalue, $templates);
                }
            }
        }
        return $templates;
    }

    public function print_elementor_submenu($template_ids) {
        if (!empty($template_ids)) {
            $type = 'template';
            foreach ($template_ids as $ekey => $id) {
                $edit_link = $this->get_element_link_by_id($id, $type);
                $name = $this->get_element_name_by_id($id, $type);
                ?>
                <li id="wp-admin-bar-elementor-template-edit-<?php echo $id; ?>">
                    <a class="ab-item" href="<?php echo $edit_link; ?>" target="_blank">
                        <!--<i class="eicon-pencil" style="font-family: eicons;"></i>-->
                        <span class="elementor-edit-link-title"><?php echo get_the_title($id); ?></span>
                        <?php
                        $document = \Elementor\Plugin::$instance->documents->get($id);
                        if ($document) {
                            ?>
                            <span class="elementor-edit-link-type"><?php echo $document::get_title(); ?></span>
                        <?php } ?>
                    </a>
                </li>
                <?php
            }
            
            $reporter = new \Elementor\Modules\System_Info\Reporters\Wordpress();
            $memory_limit = $reporter->get_memory_limit();
            $memory_limit = empty($memory_limit['value']) ? WP_MEMORY_LIMIT : $memory_limit['value'];            
            ?>
                <li id="wp-admin-bar-elementor-memory">
                    <a class="ab-item" href="<?php echo admin_url( 'admin.php?page=elementor-system-info'); ?>" target="_blank">
                        <!--<i class="eicon-pencil" style="font-family: eicons;"></i>-->
                        <span class="elementor-edit-link-title">Memory</span>
                        <span class="elementor-edit-link-type"><?php echo size_format(memory_get_usage(), 0); ?> / <?php echo $memory_limit; ?></span>
                    </a>
                </li>
            <?php
        }
    }

    public function parse_elementor_data() {
        if (self::$elementor_data_current != 'nerd') {
            $tmp = array();
            foreach (self::$elementor_data as $ekey => $edata) {
                $kpos = explode(' > ', $ekey);
                $tmp = Utils::set_array_value($tmp, $kpos, $edata);
            }
            self::$elementor_data = $tmp;
            self::$elementor_data_current = 'nerd';
        }
        return self::$elementor_data;
    }

    public function print_frontend_inspector() {

        wp_register_style(
                'font-awesome',
                ELEMENTOR_URL . 'assets/lib/font-awesome/css/font-awesome.min.css',
                [],
                '4.7.0'
        );
        wp_enqueue_style('font-awesome');

        wp_register_script(
                'tipsy',
                ELEMENTOR_ASSETS_URL . 'lib/tipsy/tipsy.min.js',
                [
                    'jquery',
                ],
                '1.0.0',
                true
        );
        wp_enqueue_script('tipsy');

        wp_enqueue_style('e-addons-inspector');
        wp_enqueue_script('e-addons-inspector');

        $this->parse_elementor_data();
        //echo '<pre>';var_dump(self::$elements);echo '</pre>';

        self::$elements_categories = \Elementor\Plugin::$instance->elements_manager->get_categories();
        //var_dump($categories);
        ?>
        <div id="elementor-navigator" style="display: none;">
            <div id="elementor-navigator__inner">
                <div id="elementor-navigator__header">
                    <i id="elementor-navigator__toggle-all" class="eicon-expand" data-elementor-action="expand"></i>
                    <div id="elementor-navigator__header__title"><i class="eadd-frontend-inspector"></i> <?php _e('Inspector', 'elementor'); ?></div>
                    <i id="elementor-navigator__close" class="eicon-close"></i>
                </div>
                <div id="elementor-navigator__elements">
                    <div data-model-cid="c44" class="elementor-navigator__element elementor-navigator__element--has-children">
                        <?php $this->print_elementor_navigator(self::$elementor_data); ?>
                    </div>
                </div>
                <!--<div id="elementor-navigator__footer">
                    <i class="eicon-ellipsis-h"></i>
                </div>-->
            </div>
        </div>
        <?php
    }

    public function print_elementor_navigator($elementor_data, $template_id = 0) {
        if (!empty($elementor_data)) {
            if (is_array($elementor_data)) {
                echo '<ul class="elementor-navigator__elements">';
                foreach ($elementor_data as $ekey => $evalue) {
                    list($type, $id) = explode('-', $ekey);
                    if ($type == 'template') {
                        $template_id = $id;
                    }
                    $name = $this->get_element_name_by_id($id, $type);
                    $target = '.elementor-element-' . $id;
                    if ($type == 'template') {
                        $target = '.elementor.elementor-' . $id;
                    }
                    $aname = $this->get_element_title_by_id($id, $type, $name, $template_id);
                    $element_icon = $this->get_element_icon_by_id($id, $type, $name);
                    $edit_link = $this->get_element_link_by_id($template_id, $type, $id);

                    if (!self::$elementor_capability && isset(self::$elements_hidden[$id]) && empty(self::$elements_hidden[$id]['fallback'])) {
                        continue;
                    }
                    ?>

                    <li class="elementor-navigator__element elementor-navigator__element-<?php echo $type . (is_array($evalue) ? ' elementor-navigator__element--has-children' : '') . (self::$elementor_capability && isset(self::$elements_hidden[$id]) && empty(self::$elements_hidden[$id]['fallback']) ? ' elementor-navigator__element--hidden' : ''); ?>">
                        <div class="elementor-navigator__item elementor-active<?php echo self::$elementor_capability && isset(self::$elements_hidden[$id]) ? ' e-addons-hidden' : ''; ?>" data-target="<?php echo $target; ?>">
                            <?php if (is_array($evalue)) { ?>
                                <a href="#" class="elementor-navigator__element__list-toggle"><i class="eicon-sort-down"></i></a>
                            <?php } else { ?>
                                <span class="elementor-navigator__element__list-spacer"></span>
                                <?php
                            }
                            ?>

                            <span class="elementor-navigator__element__element-type"><i class="<?php echo $element_icon; ?>"></i></span>
                            <span class="elementor-navigator__element__title"><span class="elementor-navigator__element__title__text"><?php echo $aname; ?></span></span>


                            <div class="elementor-navigator__element__indicators">
                                <?php if (self::$elementor_capability) { ?>
                                    <div class="elementor-navigator__element__indicator" data-section="section_edit" original-title="Edit">
                                        <a class="elementor-navigator__element__edit" href="<?php echo $edit_link; ?>" target="_blank"><i class="eicon-pencil"></i></a>
                                    </div>
                                <?php } ?>

                                <div class="elementor-navigator__element__indicator elementor-navigator__element__indicator__info" data-section="section_info" original-title="Info">
                                    <a class="elementor-navigator__element__info"><i class="eicon-info-circle"></i></a>
                                </div>

                                <div class="elementor-navigator__element__indicator" data-section="section_toggle" original-title="Toggle" <?php if (!self::$elementor_capability || !isset(self::$elements_hidden[$id])) { ?>style="display: none;"<?php } ?>>
                                    <a class="elementor-navigator__element__toggle"><i class="fa fas fa-eye<?php if (isset(self::$elements_hidden[$id]) && empty(self::$elements_hidden[$id]['fallback'])) { ?>-slash<?php } ?>"></i></a>
                                </div>
                            </div>

                        </div>

                        <?php $this->print_elementor_navigator($evalue, $template_id); ?>

                        <div class="elementor-navigator__element__infobox">
                            <div class="elementor-navigator__header">
                                <i class="eicon-close elementor-navigator__close"></i>
                                <div class="elementor-navigator__header__title"><div class="e-title-h3"><?php echo $aname; ?></div></div>
                            </div>
                            <div class="elementor-navigator__element__infobox__body">
                                <i class="<?php echo $element_icon; ?> elementor-navigator__element__icon"></i>
                                <div class="e-title-h4"><?php echo ucfirst($type); ?></div>
                                <div class="e-title-h5">ID: <?php echo $id; ?></div>
                                <hr>
                                <div class="elementor-navigator__element__infobox__details">
                                    <div class="e-title-h6"><?php _e('Details', 'elementor'); ?>:</div>
                                    <dl>
                                        <dt><?php _e('Name', 'elementor'); ?></dt> <dd><?php echo $name; ?></dd>
                                        <?php if (!empty(self::$elements_time[$id]) && !empty(self::$elements_time[$id]['start']) && !empty(self::$elements_time[$id]['end'])) { ?>
                                            <dt><?php _e('Time', 'elementor'); ?></dt> <dd><?php echo self::$elements_time[$id]['end'] - self::$elements_time[$id]['start']; ?></dd>
                                        <?php } ?>
                                        <dt><?php _e('Element Count', 'elementor'); ?></dt> <dd><?php echo self::$elements[$type][$name][$id]; ?></dd>
                                        <dt><?php _e('Type Count', 'elementor'); ?></dt> <dd><?php echo count(self::$elements[$type][$name]); ?></dd>
                                        <?php
                                        if ($type == 'template') {
                                            $document = \Elementor\Plugin::$instance->documents->get($id);
                                            if ($document) {
                                                ?>
                                                <dt><?php _e('Type', 'elementor'); ?></dt> <dd><?php echo $document::get_title(); ?></dd>
                                                <?php if (self::$elementor_capability) { ?>
                                                    <dt><?php _e('Created on', 'elementor'); ?></dt> <dd><?php echo $document->get_post()->post_date; ?></dd>
                                                    <dt><?php _e('Author', 'elementor'); ?></dt> <dd><a href="<?php echo Utils::get_user_url($document->get_post()->post_author); ?>" target="_blank"><?php echo get_the_author_meta('display_name', $document->get_post()->post_author); ?></a></dd>
                                                    <?php if ($document->get_post()->post_date != $document->get_post()->post_modified) { ?>
                                                        <dt><?php _e('Modified on', 'elementor'); ?></dt> <dd><?php echo $document->get_post()->post_modified; ?></dd>
                                                    <?php } ?>
                                                    <?php
                                                    $modified_author_id = get_the_modified_author($id);
                                                    $modified_author_id = get_post_meta(get_post()->ID, '_edit_last', true);
                                                    if ($modified_author_id && $document->get_post()->post_author != $modified_author_id) {
                                                        ?>
                                                        <dt><?php _e('Modified by', 'elementor'); ?></dt>  <dd><a href="<?php echo Utils::get_user_url($modified_author_id); ?>" target="_blank"><?php echo get_the_author_meta('display_name', $modified_author_id); ?></a></dd>
                                                    <?php
                                                    }
                                                }
                                                ?>
                                                <dt><?php _e('Status', 'elementor'); ?></dt> <dd><?php echo $document->get_post()->post_status; ?></dd>
                                                <?php
                                            }
                                        }
                                        $category = 'basic';
                                        if ($type == 'widget') {
                                            $widget = $this->get_widget_by_id($id);
                                            $categories = $widget->get_categories();

                                            if (!empty($categories)) {
                                                $category = '';
                                                foreach ($categories as $ckey => $acat) {
                                                    if ($ckey) {
                                                        $category .= ', ';
                                                    }
                                                    $category .= !empty(self::$elements_categories[$acat]['title']) ? self::$elements_categories[$acat]['title'] : $acat;
                                                }
                                            }
                                            ?>
                                            <dt><?php _e('Category', 'elementor'); ?></dt> <dd><?php echo $category; ?></dd>
                                            <?php
                                        } else {
                                            $categories = array($category);
                                        }
                                        ?>
                                    </dl>
                                </div>
                    <?php if (isset(self::$elements_hidden[$id]) && self::$elementor_capability) { ?>
                                    <div class="elementor-navigator__element__infobox__display">
                                        <div class="e-title-h6"><i class="eadd-extension-display"></i> <?php _e('Display', 'elementor'); ?>:</div>
                                        <dt>
                                        <?php if (empty(self::$elements_hidden[$id])) { ?>
                                            <dt><?php _e('Hidden', 'elementor'); ?></dt> <dd><?php _e('Always', 'elementor'); ?></dd>
                                            <?php
                                        } else {
                                            $element = Utils::get_element_instance_by_id($id, $template_id);
                                            $element_settings = $element->get_settings_for_display();
                                            foreach (self::$elements_hidden[$id]['triggers'] as $ckey => $cond) {
                                                $control = $element->get_controls('e_display_repeater');
                                                //var_dump($control); die();
                                                ?>
                                                <dt><?php echo empty($control['fields'][$ckey]['label']) ? $cond : $control['fields'][$ckey]['label']; ?></dt> <dd><?php
                                                    if (!empty($element_settings[$ckey])) {
                                                        echo Utils::to_string($element_settings[$ckey]);
                                                    }
                                                    ?></dd>
                                                <?php
                                            }
                                        }
                                        ?>
                                        <dt><?php _e('Fallback', 'elementor'); ?></dt> <dd><?php echo empty(self::$elements_hidden[$id]['fallback']) ? __('No', 'elementor') : __('Yes', 'elementor'); ?></dd>
                                        </dt>
                                    </div>
                    <?php } ?>


                                <div class="elementor-navigator__element__footer">
                                    <a class="elementor-button elementor-size-xs elementor-navigator__element__infobox__toggle tooltip-target" aria-hidden="true" data-tooltip="Toggle" original-title="Toggle" href="#">
                                        <i class="fa fas fa-eye<?php if (isset(self::$elements_hidden[$id]) && empty(self::$elements_hidden[$id]['fallback'])) { ?>-slash<?php } ?>"></i>
                                    </a>

                                    <?php
                                    $element_settings = false;
                                    /* if (self::$elementor_capability) {
                                      if (!empty(self::$elements_settings[$id])) {
                                      $element_settings = self::$elements_settings[$id];
                                      if ($type != 'template') {
                                      $element_settings = Utils::get_element_instance_by_id($id, $template_id);
                                      }
                                      }
                                      }
                                      if (!empty($element_settings)) {
                                      ?>
                                      <textarea class="elementor-navigator__element__settings" id="elementor-navigator__element__settings_<?php echo $id; ?>"><?php echo '[' . wp_json_encode($element_settings) . ']'; ?></textarea>
                                      <?php } */
                                    ?>

                                    <?php
                                    if (self::$elementor_capability) {
                                        if (!empty($element_settings)) {
                                            ?>
                                            <button class="elementor-button elementor-button-info elementor-size-xs elementor-navigator__element__infobox__copy_mini tooltip-target" aria-hidden="true" data-tooltip="Copy" original-title="Copy" data-clipboard-action="copy" data-clipboard-target="#elementor-navigator__element__settings_<?php echo $id; ?>">
                                                <i class="eicon-copy"></i>
                                            </button>
                        <?php }
                        ?>
                                        <a class="elementor-button elementor-button-warning elementor-size-xs elementor-navigator__element__infobox__edit" href="<?php echo $edit_link; ?>" target="_blank">
                                            <i class="eicon-pencil"></i> <?php _e('Edit', 'elementor'); ?>
                                        </a>
                                        <?php
                                    } else {
                                        if (!empty($element_settings)) {
                                            ?>
                                            <button class="elementor-button elementor-button-info elementor-size-xs elementor-navigator__element__infobox__copy" data-clipboard-action="copy" data-clipboard-target="#elementor-navigator__element__settings_<?php echo $id; ?>">
                                                <i class="eicon-copy"></i> <?php _e('Copy', 'elementor'); ?>
                                            </button>
                                            <?php
                                        }
                                    }
                                    ?>
                                </div>

                            </div>
                            <div class="elementor-align-center elementor-navigator__element__logo">
                                <?php
                                foreach ($categories as $acat) {
                                    switch ($acat) {
                                        case 'general':
                                        case 'basic':
                                            echo '<a href="https://elementor.com/" target="_blank"><i class="eicon-elementor-square"></i></a>';
                                            break;
                                        case 'pro-elements':
                                            echo '<a href="https://elementor.com/pro/" target="_blank"><i class="eicon-elementor-square"></i><i class="eicon-pro-icon"></i></a>';
                                            break;
                                        default:
                                            if (substr($acat, 0, 8) == 'e-addons') {
                                                echo '<a href="https://e-addons.com" target="_blank"><i class="eadd-logo-e-addons"></i></a>';
                                            } else {
                                                if (!empty(self::$elements_categories[$acat]['icon'])) {
                                                    echo '<i class="' . self::$elements_categories[$acat]['icon'] . '"></i>';
                                                }
                                            }
                                    }
                                }
                                ?>
                            </div>
                                    <?php if ($type == 'widget') { ?>
                                <div class="elementor-align-center elementor-navigator__element__help"><a class="elementor-navigator__element__help__link" href="<?php echo $widget->get_custom_help_url() ? $widget->get_custom_help_url() : $widget->get_help_url(); ?>" target="_blank"><?php _e('Need Help', 'elementor'); ?> <i class="eicon-help-o"></i></a></div>
                    <?php } ?>
                        </div>

                    </li>
                    <?php
                }
                echo '</ul>';
            }
        }
    }

    public function get_element_icon_by_id($id, $type, $name) {
        switch ($type) {
            case 'column':
            case 'section':
                return 'eicon-' . $type;
                break;
            case 'template':
                return 'eicon-inner-section';
                break;
            case 'widget':
                if ($name) {
                    $widget = \Elementor\Plugin::instance()->widgets_manager->get_widget_types($name);
                    if ($widget) {
                        return 'eicon-widget ' . $widget->get_icon();
                    }
                }
        }
        return 'eicon-widget eicon-square';
    }

    public function get_widget_by_id($id) {
        $name = $this->get_element_name_by_id($id, 'widget');
        if ($name) {
            $widget = \Elementor\Plugin::instance()->widgets_manager->get_widget_types($name);
            if ($widget) {
                return $widget;
            }
        }
        return false;
    }

    public function get_element_title_by_id($id, $type, $name, $template_id = 0) {
        if ($type == 'template') {
            return get_the_title($id);
        }
        if ($type == 'column') {
            return __('Column', 'elementor');
        }
        if ($type == 'section') {
            $settings = Utils::get_settings_by_element_id($id, $template_id);
            //var_dump($settings);
            if (!empty($settings['_title'])) {
                return $settings['_title'];
            }
            return __('Section', 'elementor');
        }
        if ($type == 'widget') {
            if ($name) {
                $widget = \Elementor\Plugin::instance()->widgets_manager->get_widget_types($name);
                if ($widget) {
                    return $widget->get_title();
                }
            }
        }
        return $name;
    }

    public function get_element_name_by_id($id, $type) {
        if ($type == 'widget') {
            foreach (self::$elements[$type] as $name => $ename) {
                foreach ($ename as $eid => $ecount) {
                    if ($eid == $id) {
                        return $name;
                    }
                }
            }
        }
        if ($type == 'template') {
            $post = get_post($id);
            if ($post) {
                return $post->post_name;
            }
        }
        return $type;
    }

    public function get_element_link_by_id($template_id, $type, $id = false) {
        $edit_link = get_edit_post_link($template_id);
        $pieces = explode('action=', $edit_link, 2);
        $edit_link = reset($pieces) . 'action=elementor';
        if ($type != 'template' && $id) {
            $edit_link .= '&element=' . $id;
        }
        return $edit_link;
    }
    
    public static function get_current_element() {
        $element = self::$elementor_current;
        if ($element) {
            return $element;
        }
        return false;
    }

}
