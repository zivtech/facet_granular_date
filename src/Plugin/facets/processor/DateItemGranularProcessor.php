<?php

namespace Drupal\facet_granular_date\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facet_granular_date\Plugin\facets\query_type\SearchApiDateGranular;
use Drupal\search_api\Entity\Index;
use Drupal\facets\Result\Result;
use Drupal\Core\Url;

/**
 * Provides a processor for granular dates.
 *
 * @FacetsProcessor(
 *   id = "date_granular_item",
 *   label = @Translation("Date item granular processor"),
 *   description = @Translation("Year -> Year-month granularity."),
 *   stages = {
 *     "build" = 1
 *   }
 * )
 */
class DateItemGranularProcessor extends ProcessorPluginBase implements BuildProcessorInterface {

    /**
     * {@inheritdoc}
     *
     * This is a custom facet processor that allows for drill down granularity.
     * Right now you can click on a year, and it will create facets for each
     * month of that year that has content.
     *
     */
    public function build(FacetInterface $facet, array $results) {
        // We only want this to run if a facet is active.
        // Otherwise don't alter the results.
        if(!empty($facet->getActiveItems())) {
            // Remove all other years.
            foreach ($results as $key => $result) {
                if(!in_array($key, $facet->getActiveItems())) {
                    unset($results[$key]);
                }
            }
            // Get the params from facets already selected.
            $params = \Drupal::request()->query->all();
            // Get the active facet year (and month if selected).
            $facetActiveItems = $facet->getActiveItems();
            $activeItem = array_pop($facetActiveItems);
            $hyphonCount = substr_count($activeItem, '-');
            // TODO - Change to switch
            switch($hyphonCount) {
                case 0:
                    $granularity = 'year';
                    //TODO - Fix count here.
                    // Create Year facet.
                    $results[$activeItem] = new Result($facet, $activeItem, $activeItem, 0);
                    $this->createFacets($facet, $params, $activeItem, $granularity, $results);
                    if ($this->checkDateFacetsCount($params) > 1) {
                        $this->unsetAllInactiveFacetResults($results);
                    }
                    $config = $this->getConfiguration();
                    $config['granularity'] = SearchApiDateGranular::FACETAPI_DATE_YEAR;
                    $this->setConfiguration($config);
                    break;
                case 1:
                    // TODO - Create the exploded one here.
                    $granularity = 'month';
                    $explodedActiveItem = explode('-', $activeItem);
                    $yearValue = $explodedActiveItem[0];
                    //TODO - Fix count here.
                    // Create Year facet.
                    $results[$yearValue] = new Result($facet, $yearValue, $yearValue, 0);
                    $results[$yearValue]->setActiveState(TRUE);
                    //TODO - Fix count
                    // Create month facet.
                    $monthDisplay = \DateTime::createFromFormat('Y-m', $activeItem)->format('F Y');
                    $results[$activeItem] = new Result($facet, $activeItem, $monthDisplay, 0);
                    $results[$activeItem]->setActiveState(TRUE);
                    $this->createFacets($facet, $params, $activeItem, $granularity, $results);
                    break;
                case 2:
                    $granularity = 'day';
                    // TODO - Need helper for this.
                    $explodedActiveItem = explode('-', $activeItem);
                    $yearValue = $explodedActiveItem[0];
                    $monthValue = $explodedActiveItem[1];
                    // TODO - Should extract this to helper... CreateActiveFacets or so and use it for Year Month & day...
                    // TODO - Get count. Another reason to create a helper...
                    // Create Year facet.
                    $results[$yearValue] = new Result($facet, $yearValue, $yearValue, 0);
                    $results[$yearValue]->setActiveState(TRUE);
                    //TODO - Fix count
                    // Create month facet.
                    $monthDisplay = \DateTime::createFromFormat('Y-m', $yearValue . '-' . $monthValue)->format('F Y');
                    $results[$yearValue . '-' . $monthValue] = new Result($facet, $yearValue . '-' . $monthValue, $monthDisplay, 0);
                    $results[$yearValue . '-' . $monthValue]->setActiveState(TRUE);
                    // Create day facet.
                    // TODO fix count.
                    $dayDisplay = \DateTime::createFromFormat('Y-m-d', $activeItem)->format('jS  \o\f F Y');
                    $results[$activeItem] = new Result($facet, $activeItem, $dayDisplay, 0);
                    $results[$activeItem]->setActiveState(TRUE);
                    // TODO is this needed?
                    $config = $this->getConfiguration();
                    $config['granularity'] = SearchApiDateGranular::FACETAPI_DATE_DAY;
                    $this->setConfiguration($config);
                    break;

            }
        }
        return $results;
    }

    private function getActiveMonth($params) {
        foreach ($params['f'] as $param) {
            $pos = strpos($param, 'issue_date');
            $pos2 = strpos($param, '-');
            if ($pos !== FALSE && $pos2 !== FALSE) {
                $value = explode("-", $param);
                return $value[1];
            }
        }
    }

    /**
     * Helper function.
     *
     * For the other facets in the site, check the value and add
     * it as a query condition. Used to get the correct count for the new
     * facet items.
     * Ideally this will be removed when we get the proper count.
     *
     * @param $param
     * @param $conditions
     * @param $query
     */
    private function processConditions($param, $conditions, &$query) {
        foreach ($conditions as $field => $condition) {
            $pos = strpos($param, $condition);
            if ($pos !== false) {
                $value = explode(":", $param);
                $query->addCondition($field, $value[1], '=');
            }
        }
    }

    /**
     * Helper function.
     *
     * Checks how many issue date facets are currently active.
     *
     * @param $params
     * @return bool
     */
    private function checkDateFacetsCount($params) {
        $count = 0;
        foreach ($params['f'] as $param) {
            $pos = strpos($param, 'issue_date');
            if ($pos !== FALSE) {
                $count++;
            }
        }
        return $count;
    }

    /**
     * Helper function.
     *
     * Removes all inactive facets for the issue date facet.
     *
     * This should just leave the year and month facets.
     *
     * @param $results
     */
    private function unsetAllInactiveFacetResults(&$results) {
        foreach ($results as $key => $result) {
            if(!$result->isActive()) {
                unset($results[$key]);
            }
        }
    }

    private function createFacets($facet, $params, $activeItems, $granularity, &$results) {
        switch ($granularity) {
            case 'year':
                for ($i = 1; $i < 13; $i++) {

                    $iPlusOne = $i + 1;
                    // Get the  current and next month Objects + Set the time to 00:00:00
                    $month = \DateTime::createFromFormat('d-n-Y', '01-' . $i . '-' . $activeItems);
                    $month->setTime(0, 0, 0);
                    $nextMonth = \DateTime::createFromFormat('d-n-Y', '01-' . $iPlusOne . '-' . $activeItems);
                    $nextMonth->setTime(0, 0, 0);
                    if (!empty($month) && !empty($nextMonth)) {
                        $month->setTime(0, 0, 0);
                        $nextMonth->setTime(0, 0, 0);
                        $monthTimestamp = $month->getTimestamp();
                        $nextMonthTimestamp = $nextMonth->getTimestamp();
                        // Get the month in the n format (month number, without leading 0).
                        $monthObj = \DateTime::createFromFormat('n', $i);
                        // Human readable month.
                        $monthName = $monthObj->format('F');
                        // Month with leading 0.
                        $monthNumber = $monthObj->format('m');
                        // Create the query on the index.
                        // We need to use this index to return the result count for
                        // the newly created facets, since they aren't found automatically.

                        //TODO - In my option - This is the hardest part of
                        // open sourcing this code. I've scrubbed the data
                        // but this is where I had to make a lot of assumptions
                        // about the original site. This is all about getting
                        // the count for each facet. There must be a better
                        // way :/.

                        // Get the index ID from the facet. I think this should
                        // be safe without an !empty check since a facet
                        // always has to have an Index.
                        $indexId = $facet->getFacetSource()
                            ->getIndex()
                            ->id();
                        $query = Index::load($indexId)->query();
                        $field = $facet->getFieldIdentifier();
                        $query->addCondition('status', 1);
                        $query->addCondition($field, [$monthTimestamp, $nextMonthTimestamp], 'BETWEEN');
                        // Add the extra facet information to get the count data.
                        foreach ($params['f'] as $param) {
                            // Other facets.
                            $conditions = [
                                'type' => 'content_type',
                                'field_name 1' => 'field1',
                                'field_name 2' => 'field2',
                                'field_name_3' => 'field3',
                            ];
                            // Helper function that processes the conditions for the query
                            // based on the other facet results
                            $this->processConditions($param, $conditions, $query);
                        }
                        // Run the query.
                        $entities = $query->execute();
                        if (!empty($results) && $entities->getResultCount() > 0) {
                            // TODO - This is temporary data. Make this the request URI.
                            $url = Url::fromUri('internal://node/1');
                            if (!empty(reset($results)->getUrl())) {
                                $url = clone reset($results)->getUrl();
                            }

                            $options = $url->getOptions();
                            unset($options['query']['f']);
                            $options['query']['f'][] = $field . ':' . $activeItems . '-' . $monthNumber;
                            $url->setOptions($options);
                            $results[$activeItems . '-' . $monthNumber] = new Result($facet, $activeItems . '-' . $monthNumber, $monthName . ' ' . $activeItems, $entities->getResultCount());
                            $results[$activeItems . '-' . $monthNumber]->setUrl($url);
                        }
                    }
                }
                break;
            case 'month':
                $activeMonth = $this->getActiveMonth($params);
                // TODO - Make this a helper. Use it a lot.
                $explodedActiveItem = explode('-', $activeItems);
                $yearValue = $explodedActiveItem[0];
                $daysInCurrentMonth = cal_days_in_month(CAL_GREGORIAN, $activeMonth, $yearValue);
                // Process days. PHP DateTime days start at 1. So start there.
                for ($i = 1; $i < $daysInCurrentMonth; $i++) {
                    $day = \DateTime::createFromFormat('j', $i);
                    $month = \DateTime::createFromFormat('m', $activeMonth);
                    $iPlusOne = $i + 1;
                    // Get the  current and next month Objects + Set the time to 00:00:00
                    $day = \DateTime::createFromFormat('Y-m-d', $activeItems . '-' . $i);
                    $day->setTime(0, 0, 0);
                    $nextDay = \DateTime::createFromFormat('Y-m-d', $activeItems . '-' . $iPlusOne);
                    $nextDay->setTime(0, 0, 0);
                    // TODO - Need to add a count check here - We only need to be displaying days with results.
                    if (!empty($day) && !empty($nextDay)) {
                        $dayTimestamp = $day->getTimestamp();
                        $nextDayTimestamp = $nextDay->getTimestamp();
                        // TODO - Code duplication. We need to extract this to a helper function now...
                        $indexId = $facet->getFacetSource()
                            ->getIndex()
                            ->id();
                        $dayQuery = Index::load($indexId)->query();
                        $dayField = $facet->getFieldIdentifier();
                        $dayQuery->addCondition('status', 1);
                        $dayQuery->addCondition($dayField, [$dayTimestamp, $nextDayTimestamp], 'BETWEEN');
                        // Add the extra facet information to get the count data.
                        foreach ($params['f'] as $param) {
                            // Other facets.
                            $conditions = [
                                'type' => 'content_type',
                                'field_name 1' => 'field1',
                                'field_name 2' => 'field2',
                                'field_name_3' => 'field3',
                            ];
                            // Helper function that processes the conditions for the query
                            // based on the other facet results
                            $this->processConditions($param, $conditions, $dayQuery);
                        }
                        // Run the query.
                        $dayEntities = $dayQuery->execute();

                        if ($dayEntities->getResultCount() > 0) {
                            $explodedActiveItem = explode('-', $activeItems);
                            $yearValue = $explodedActiveItem[0];
                            $displayValue = $day->format('jS') . ' of ' . $month->format('F') . ' ' . $yearValue;
                            $results[$activeItems . '-' . $day->format('d')] = new Result($facet, $activeItems . '-' . $day->format('d'), $displayValue, $dayEntities->getResultCount());
                        }
                    }
                }
                $activeItemCode = $activeItems;
                if (!empty($activeItemCode) && empty($results[$activeItemCode])) {
                    //TODO - Fix count here.
                    $display = \DateTime::createFromFormat('Y-m', $activeItemCode)->format('F Y');
                    $results[$activeItemCode] = new Result($facet, $activeItemCode, $display, 0);
                }
                if (!empty($activeItemCode) && !empty($results[$activeItemCode])) {
                    $results[$activeItemCode]->setActiveState(TRUE);
                }
                break;
            case 'day':
                break;

        }
    }

    /**
     * Human readable array of granularity options.
     *
     * @return array
     *   An array of granularity options.
     */
    private function granularityOptions() {
        return [
            SearchApiDateGranular::FACETAPI_DATE_YEAR => $this->t('Year'),
            SearchApiDateGranular::FACETAPI_DATE_MONTH => $this->t('Month'),
            SearchApiDateGranular::FACETAPI_DATE_DAY => $this->t('Day'),
            SearchApiDateGranular::FACETAPI_DATE_HOUR => $this->t('Hour'),
            SearchApiDateGranular::FACETAPI_DATE_MINUTE => $this->t('Minute'),
            SearchApiDateGranular::FACETAPI_DATE_SECOND => $this->t('Second'),
        ];
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
        $this->getConfiguration();
        $build = [];
        return $build;
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryType() {
        return 'date';
    }

    /**
     * {@inheritdoc}
     */
    public function defaultConfiguration() {
        return [
            'date_display' => 'actual_date',
            'granularity' => SearchApiDateGranular::FACETAPI_DATE_YEAR,
            'date_format' => '',
        ];
    }

}