<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License 3.0 (AFL-3.0)
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade PrestaShop to newer
 * versions in the future. If you wish to customize PrestaShop for your
 * needs please refer to https://devdocs.prestashop.com/ for more information.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License 3.0 (AFL-3.0)
 */
if (!defined('_PS_VERSION_')) {
    exit;
}

class dashgoals extends Module
{
    protected static $month_labels = [];
    protected static $types = ['traffic', 'conversion', 'avg_cart_value'];

    protected static $real_color = ['#9E5BA1', '#00A89C', '#3AC4ED', '#F99031'];
    protected static $more_color = ['#803E84', '#008E7E', '#20B2E7', '#F66E1B'];
    protected static $less_color = ['#BC77BE', '#00C2BB', '#51D6F2', '#FBB244'];

    public function __construct()
    {
        $this->name = 'dashgoals';
        $this->tab = 'administration';
        $this->version = '2.0.2';
        $this->author = 'PrestaShop';

        parent::__construct();

        $this->displayName = $this->trans('Dashboard Goals', [], 'Modules.Dashgoals.Admin');
        $this->description = $this->trans('Enrich your stats, add a block with your store’s forecast to always step ahead!', [], 'Modules.Dashgoals.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.1.0', 'max' => _PS_VERSION_];

        self::$month_labels = [
            '01' => $this->trans('January', [], 'Modules.Dashgoals.Admin'),
            '02' => $this->trans('February', [], 'Modules.Dashgoals.Admin'),
            '03' => $this->trans('March', [], 'Modules.Dashgoals.Admin'),
            '04' => $this->trans('April', [], 'Modules.Dashgoals.Admin'),
            '05' => $this->trans('May', [], 'Modules.Dashgoals.Admin'),
            '06' => $this->trans('June', [], 'Modules.Dashgoals.Admin'),
            '07' => $this->trans('July', [], 'Modules.Dashgoals.Admin'),
            '08' => $this->trans('August', [], 'Modules.Dashgoals.Admin'),
            '09' => $this->trans('September', [], 'Modules.Dashgoals.Admin'),
            '10' => $this->trans('October', [], 'Modules.Dashgoals.Admin'),
            '11' => $this->trans('November', [], 'Modules.Dashgoals.Admin'),
            '12' => $this->trans('December', [], 'Modules.Dashgoals.Admin'),
        ];
    }

    public function install()
    {
        Configuration::updateValue('PS_DASHGOALS_CURRENT_YEAR', date('Y'));
        for ($month = '01'; $month <= 12; $month = sprintf('%02d', (int) $month + 1)) {
            $key = Tools::strtoupper('dashgoals_traffic_' . $month . '_' . date('Y'));
            if (!ConfigurationKPI::get($key)) {
                ConfigurationKPI::updateValue($key, 600);
            }
            $key = Tools::strtoupper('dashgoals_conversion_' . $month . '_' . date('Y'));
            if (!ConfigurationKPI::get($key)) {
                ConfigurationKPI::updateValue($key, 2);
            }
            $key = Tools::strtoupper('dashgoals_avg_cart_value_' . $month . '_' . date('Y'));
            if (!ConfigurationKPI::get($key)) {
                ConfigurationKPI::updateValue($key, 80);
            }
        }

        // Prepare tab
        $tab = new Tab();
        $tab->active = true;
        $tab->class_name = 'AdminDashgoals';
        $tab->name = [];
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = 'Dashgoals';
        }
        $tab->id_parent = -1;
        $tab->module = $this->name;

        return
            $tab->add()
            && parent::install()
            && $this->registerHook('dashboardZoneTwo')
            && $this->registerHook('dashboardData')
            && $this->registerHook('actionAdminControllerSetMedia')
        ;
    }

    public function uninstall()
    {
        $id_tab = (int) Tab::getIdFromClassName('AdminDashgoals');
        if ($id_tab) {
            $tab = new Tab($id_tab);
            $tab->delete();
        }

        return parent::uninstall();
    }

    public function hookActionAdminControllerSetMedia()
    {
        if (get_class($this->context->controller) == 'AdminDashboardController') {
            $this->context->controller->addJs($this->_path . 'views/js/' . $this->name . '.js');
        }
    }

    public function setMonths($year)
    {
        $months = [];
        for ($i = '01'; $i <= 12; $i = sprintf('%02d', (int) $i + 1)) {
            $months[$i . '_' . $year] = ['label' => self::$month_labels[$i], 'values' => []];
        }

        foreach (self::$types as $type) {
            foreach ($months as $month => &$month_row) {
                $key = 'dashgoals_' . $type . '_' . $month;
                if (Tools::isSubmit('submitDashGoals')) {
                    ConfigurationKPI::updateValue(Tools::strtoupper($key), (float) Tools::getValue($key));
                }
                $month_row['values'][$type] = ConfigurationKPI::get(Tools::strtoupper($key));
            }
        }

        return $months;
    }

    public function hookDashboardZoneTwo($params)
    {
        $year = Configuration::get('PS_DASHGOALS_CURRENT_YEAR');
        $months = $this->setMonths($year);

        $this->context->smarty->assign(
            [
                'colors' => self::$real_color,
                'currency' => $this->context->currency,
                'goals_year' => $year,
                'goals_months' => $months,
                'dashgoals_ajax_link' => $this->context->link->getAdminLink('AdminDashgoals'),
            ]
        );

        return $this->display(__FILE__, 'dashboard_zone_two.tpl');
    }

    public function hookDashboardData($params)
    {
        $year = ((isset($params['extra']) && $params['extra'] > 1970 && $params['extra'] < 2999) ? $params['extra'] : Configuration::get('PS_DASHGOALS_CURRENT_YEAR'));

        return ['data_chart' => ['dash_goals_chart1' => $this->getChartData($year)]];
    }

    protected function fakeConfigurationKPI_get($key)
    {
        $start = [
            'TRAFFIC' => 3000,
            'CONVERSION' => 2,
            'AVG_CART_VALUE' => 90,
        ];

        if (preg_match('/^DASHGOALS_([A-Z_]+)_([0-9]{2})/', $key, $matches)) {
            if ($matches[1] == 'TRAFFIC') {
                return $start[$matches[1]] * (1 + ($matches[2] - 1) / 10);
            } else {
                return $start[$matches[1]];
            }
        }
    }

    public function getChartData($year)
    {
        // There are stream types (different charts) and for each types there are 3 available zones (one color for the goal, one if you over perform and one if you under perfom)
        $stream_types = [
            ['type' => 'traffic', 'title' => $this->trans('Traffic', [], 'Modules.Dashgoals.Admin'), 'unit_text' => $this->trans('Visits', [], 'Admin.Shopparameters.Feature')],
            ['type' => 'conversion', 'title' => $this->trans('Conversion', [], 'Modules.Dashgoals.Admin'), 'unit_text' => ''],
            ['type' => 'avg_cart_value', 'title' => $this->trans('Average cart value', [], 'Modules.Dashgoals.Admin'), 'unit_text' => ''],
            ['type' => 'sales', 'title' => $this->trans('Sales', [], 'Admin.Global'), 'unit_text' => ''],
        ];
        $stream_zones = [
            ['zone' => 'real', 'text' => ''],
            ['zone' => 'more', 'text' => $this->trans('Goal exceeded', [], 'Modules.Dashgoals.Admin')],
            ['zone' => 'less', 'text' => $this->trans('Goal not reached', [], 'Modules.Dashgoals.Admin')],
        ];

        // We initialize all the streams types for all the zones
        $streams = [];
        $average_goals = [];

        foreach ($stream_types as $key => $stream_type) {
            $streams[$stream_type['type']] = [];
            foreach ($stream_zones as $stream_zone) {
                $streams[$stream_type['type']][$stream_zone['zone']] = [
                    'key' => $stream_type['type'] . '_' . $stream_zone['zone'],
                    'title' => $stream_type['title'],
                    'unit_text' => $stream_type['unit_text'],
                    'zone_text' => $stream_zone['text'],
                    'color' => ($stream_zone['zone'] == 'more' ? self::$more_color[$key] : ($stream_zone['zone'] == 'less' ? self::$less_color[$key] : self::$real_color[$key])),
                    'values' => [],
                    'disabled' => (isset($stream_type['type']) && $stream_type['type'] == 'sales') ? false : true,
                ];
            }

            if (isset($stream_type['type'])) {
                $average_goals[$stream_type['type']] = 0;
            }
        }

        if (Configuration::get('PS_DASHBOARD_SIMULATION')) {
            $visits = $orders = $sales = [];
            $from = strtotime(date('Y-01-01 00:00:00'));
            $to = strtotime(date('Y-12-31 00:00:00'));
            for ($date = $from; $date <= $to; $date = strtotime('+1 day', $date)) {
                $visits[$date] = round(rand(2000, 5000));
                $orders[$date] = round(rand(40, 100));
                $sales[$date] = round(rand(3000, 9000), 2);
            }

            // We need to calculate the average value of each goals for the year, this will be the base rate for "100%"
            for ($i = '01'; $i <= 12; $i = sprintf('%02d', (int) $i + 1)) {
                $average_goals['traffic'] += $this->fakeConfigurationKPI_get('DASHGOALS_TRAFFIC_' . $i . '_' . $year);
                $average_goals['conversion'] += $this->fakeConfigurationKPI_get('DASHGOALS_CONVERSION_' . $i . '_' . $year);
                $average_goals['avg_cart_value'] += $this->fakeConfigurationKPI_get('DASHGOALS_AVG_CART_VALUE_' . $i . '_' . $year);
            }
            foreach ($average_goals as &$average_goal) {
                $average_goal /= 12;
            }
            $average_goals['sales'] = $average_goals['traffic'] * $average_goals['conversion'] / 100 * $average_goals['avg_cart_value'];

            // Now we can calculate the value for every months
            for ($i = '01'; $i <= 12; $i = sprintf('%02d', (int) $i + 1)) {
                $timestamp = strtotime($year . '-' . $i . '-01');

                $month_goal = $this->fakeConfigurationKPI_get('DASHGOALS_TRAFFIC_' . $i . '_' . $year);
                $value = (isset($visits[$timestamp]) ? $visits[$timestamp] : 0);
                $stream_values = $this->getValuesFromGoals($average_goals['traffic'], $month_goal, $value, self::$month_labels[$i]);
                $goal_diff = $value - $month_goal;
                $stream_values['real']['traffic'] = $value;
                $stream_values['real']['goal'] = $month_goal;
                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['traffic'] = $value;
                $stream_values['more']['traffic'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = $goal_diff;
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = $goal_diff;
                }

                if ($value == 0) {
                    $streams['traffic']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['traffic'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }

                $month_goal = $this->fakeConfigurationKPI_get('DASHGOALS_CONVERSION_' . $i . '_' . $year);
                $value = 100 * ((isset($visits[$timestamp]) && $visits[$timestamp] && isset($orders[$timestamp]) && $orders[$timestamp]) ? ($orders[$timestamp] / $visits[$timestamp]) : 0);
                $stream_values = $this->getValuesFromGoals($average_goals['conversion'], $month_goal, $value, self::$month_labels[$i]);
                $goal_diff = $value - $month_goal;
                $stream_values['real']['conversion'] = round($value, 2);
                $stream_values['real']['goal'] = round($month_goal, 2);
                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['conversion'] = $value;
                $stream_values['more']['conversion'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                if ($value == 0) {
                    $streams['conversion']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['conversion'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }

                $month_goal = $this->fakeConfigurationKPI_get('DASHGOALS_AVG_CART_VALUE_' . $i . '_' . $year);
                $value = ((isset($orders[$timestamp]) && $orders[$timestamp] && isset($sales[$timestamp]) && $sales[$timestamp]) ? ($sales[$timestamp] / $orders[$timestamp]) : 0);
                $stream_values = $this->getValuesFromGoals($average_goals['avg_cart_value'], $month_goal, $value, self::$month_labels[$i]);
                $goal_diff = $value - $month_goal;
                $stream_values['real']['avg_cart_value'] = $value;
                $stream_values['real']['goal'] = $month_goal;
                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['avg_cart_value'] = $value;
                $stream_values['more']['avg_cart_value'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = $goal_diff;
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = $goal_diff;
                }

                if ($value == 0) {
                    $streams['avg_cart_value']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['avg_cart_value'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }

                $month_goal = $this->fakeConfigurationKPI_get('DASHGOALS_TRAFFIC_' . $i . '_' . $year) * $this->fakeConfigurationKPI_get('DASHGOALS_CONVERSION_' . $i . '_' . $year) / 100 * $this->fakeConfigurationKPI_get('DASHGOALS_AVG_CART_VALUE_' . $i . '_' . $year);
                $value = (isset($sales[$timestamp]) ? $sales[$timestamp] : 0);
                $stream_values = $this->getValuesFromGoals($average_goals['sales'], $month_goal, $value, self::$month_labels[$i]);
                $goal_diff = $value - $month_goal;
                $stream_values['real']['sales'] = $value;
                $stream_values['real']['goal'] = $month_goal;

                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['sales'] = $value;
                $stream_values['more']['sales'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = $goal_diff;
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = $goal_diff;
                }

                if ($value == 0) {
                    $streams['sales']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['sales'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }
            }
        } else {
            // Retrieve gross data from AdminStatsController
            $visits = AdminStatsController::getVisits(false, $year . date('-01-01'), $year . date('-12-31'), 'month');
            $orders = AdminStatsController::getOrders($year . date('-01-01'), $year . date('-12-31'), 'month');
            $sales = AdminStatsController::getTotalSales($year . date('-01-01'), $year . date('-12-31'), 'month');

            // We need to calculate the average value of each goals for the year, this will be the base rate for "100%"
            for ($i = '01'; $i <= 12; $i = sprintf('%02d', (int) $i + 1)) {
                $average_goals['traffic'] += ConfigurationKPI::get('DASHGOALS_TRAFFIC_' . $i . '_' . $year);
                $average_goals['conversion'] += ConfigurationKPI::get('DASHGOALS_CONVERSION_' . $i . '_' . $year) / 100;
                $average_goals['avg_cart_value'] += ConfigurationKPI::get('DASHGOALS_AVG_CART_VALUE_' . $i . '_' . $year);
            }
            foreach ($average_goals as &$average_goal) {
                $average_goal /= 12;
            }
            $average_goals['sales'] = $average_goals['traffic'] * $average_goals['conversion'] * $average_goals['avg_cart_value'];

            // Now we can calculate the value for every months
            for ($i = '01'; $i <= 12; $i = sprintf('%02d', (int) $i + 1)) {
                $timestamp = strtotime($year . '-' . $i . '-01');

                $month_goal = ConfigurationKPI::get('DASHGOALS_TRAFFIC_' . $i . '_' . $year);
                $value = (isset($visits[$timestamp]) ? $visits[$timestamp] : 0);
                $stream_values = $this->getValuesFromGoals($average_goals['traffic'], $month_goal, $value, self::$month_labels[$i]);
                $goal_diff = $value - $month_goal;
                $stream_values['real']['traffic'] = $value;
                $stream_values['real']['goal'] = $month_goal;
                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['traffic'] = $value;
                $stream_values['more']['traffic'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = $goal_diff;
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = $goal_diff;
                }

                if ($value == 0) {
                    $streams['traffic']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['traffic'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }

                $month_goal = (float) ConfigurationKPI::get('DASHGOALS_CONVERSION_' . $i . '_' . $year);
                $value = 100 * ((isset($visits[$timestamp]) && $visits[$timestamp] && isset($orders[$timestamp]) && $orders[$timestamp]) ? ($orders[$timestamp] / $visits[$timestamp]) : 0);
                $stream_values = $this->getValuesFromGoals($average_goals['conversion'] * 100, $month_goal, $value, self::$month_labels[$i]);
                $goal_diff = $value - (int) $month_goal;
                $stream_values['real']['conversion'] = round($value, 2);
                $stream_values['real']['goal'] = round($month_goal, 2);
                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['conversion'] = $value;
                $stream_values['more']['conversion'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                if ($value == 0) {
                    $streams['conversion']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['conversion'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }

                $month_goal = (int) ConfigurationKPI::get('DASHGOALS_AVG_CART_VALUE_' . $i . '_' . $year);
                $value = ((isset($orders[$timestamp]) && $orders[$timestamp] && isset($sales[$timestamp]) && $sales[$timestamp]) ? ($sales[$timestamp] / $orders[$timestamp]) : 0);
                $stream_values = $this->getValuesFromGoals($average_goals['avg_cart_value'], $month_goal, $value, self::$month_labels[$i]);
                $goal_diff = $value - $month_goal;
                $stream_values['real']['avg_cart_value'] = $value;
                $stream_values['real']['goal'] = $month_goal;
                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['avg_cart_value'] = $value;
                $stream_values['more']['avg_cart_value'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = $goal_diff;
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = $goal_diff;
                }

                if ($value == 0) {
                    $streams['avg_cart_value']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['avg_cart_value'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }

                $month_goal = (int) ConfigurationKPI::get('DASHGOALS_TRAFFIC_' . $i . '_' . $year)
                    * (float) ConfigurationKPI::get('DASHGOALS_CONVERSION_' . $i . '_' . $year)
                    / 100
                    * (int) ConfigurationKPI::get('DASHGOALS_AVG_CART_VALUE_' . $i . '_' . $year);
                $value = (isset($sales[$timestamp]) && $sales[$timestamp]) ? $sales[$timestamp] : 0;
                $stream_values = $this->getValuesFromGoals($average_goals['sales'], $month_goal, isset($sales[$timestamp]) ? $sales[$timestamp] : 0, self::$month_labels[$i]);
                $goal_diff = $value - $month_goal;
                $stream_values['real']['sales'] = $value;
                $stream_values['real']['goal'] = $month_goal;

                if ($value > 0) {
                    $stream_values['real']['goal_diff'] = round(($goal_diff * 100) / ($month_goal > 0 ? $month_goal : 1), 2);
                }

                $stream_values['less']['sales'] = $value;
                $stream_values['more']['sales'] = $value;

                if ($value > 0 && $value < $month_goal) {
                    $stream_values['less']['goal_diff'] = $goal_diff;
                } elseif ($value > 0) {
                    $stream_values['more']['goal_diff'] = $goal_diff;
                }

                if ($value == 0) {
                    $streams['sales']['less']['zone_text'] = $this->trans('Goal set:', [], 'Modules.Dashgoals.Admin');
                    $stream_values['less']['goal'] = $month_goal;
                }

                foreach ($stream_zones as $stream_zone) {
                    $streams['sales'][$stream_zone['zone']]['values'][] = $stream_values[$stream_zone['zone']];
                }
            }
        }

        // Merge all the streams before sending
        $all_streams = [];
        foreach ($stream_types as $stream_type) {
            foreach ($stream_zones as $stream_zone) {
                $all_streams[] = $streams[$stream_type['type']][$stream_zone['zone']];
            }
        }

        return ['chart_type' => 'bar_chart_goals', 'data' => $all_streams];
    }

    protected function getValuesFromGoals($average_goal, $month_goal, $value, $label)
    {
        // Initialize value for each zone
        $stream_values = [
            'real' => ['x' => $label, 'y' => 0],
            'less' => ['x' => $label, 'y' => 0],
            'more' => ['x' => $label, 'y' => 0],
        ];

        // Calculate the percentage of fullfilment of the goal
        $fullfilment = 0;
        if ($value && $month_goal) {
            $fullfilment = round($value / $month_goal, 2);
        }

        // Base rate is essential here : it determines the value of the goal compared to the "100%" of the chart legend
        $base_rate = 0;
        if ($average_goal && $month_goal) {
            $base_rate = $month_goal / $average_goal;
        }

        // Fullfilment of 1 means that we performed exactly anticipated
        if ($fullfilment == 1) {
            $stream_values['real'] = ['x' => $label, 'y' => round($base_rate, 2)];
        }
        // Fullfilment lower than 1 means that we UNDER performed
        elseif ($fullfilment < 1) {
            $stream_values['real'] = ['x' => $label, 'y' => round($fullfilment * $base_rate, 2)];
            $stream_values['less'] = ['x' => $label, 'y' => round($base_rate - ($fullfilment * $base_rate), 2)];
        }
        // Fullfilment greater than 1 means that we OVER performed
        elseif ($fullfilment > 1) {
            $stream_values['real'] = ['x' => $label, 'y' => round($base_rate, 2)];
            $stream_values['more'] = ['x' => $label, 'y' => round(($fullfilment * $base_rate) - $base_rate, 2)];
        }

        return $stream_values;
    }
}
