<?php

namespace Drupal\facet_granular_date\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
//use Drupal\facets\Plugin\facets\query_type\SearchApiDate;
use Drupal\facet_granular_date\Plugin\facets\query_type\SearchApiDateGranular;
use Drupal\search_api\Entity\Index;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\TypedData\ComplexDataDefinitionInterface;
use Drupal\Core\TypedData\DataReferenceDefinitionInterface;
use Drupal\Core\TypedData\TranslatableInterface;
use Drupal\facets\Exception\InvalidProcessorException;
use Drupal\facets\Result\Result;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Url;

/**
 * Provides a processor for dates.
 *
 * TODO - This whole class needs review. It might be mostly experimental
 *  and/or pointless.
 *
 * TODO - Gross. On review I forgot how far away from me this code got.
 *   There is a whole bunch of code duplication and grossness. Lots to
 *   do in this class.
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

        // We only want this to run if the year as already been selected.
        // Otherwise dob't alter the results.
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
            $activeItem = $facet->getActiveItems();
            // This will always be the year.
            $activeItemFirst = $activeItem[0];
            $activeItemSecond = isset($activeItem[1]) ? $activeItem[1] : NULL;
            if (!empty($activeItemFirst) && empty($activeItemSecond)) {
                //if (!empty($activeItemFirst)) {
                // Process months. PHP DateTime months start at 1. So start there.
                for ($i = 1; $i < 13; $i++) {

                    $iPlusOne = $i + 1;
                    // Get the  current and next month Objects + Set the time to 00:00:00
                    $month = \DateTime::createFromFormat('d-n-Y', '01-' . $i . '-' . $activeItemFirst);
                    $month->setTime(0,0,0);
                    $nextMonth = \DateTime::createFromFormat('d-n-Y', '01-' . $iPlusOne . '-' . $activeItemFirst);
                    $nextMonth->setTime(0,0,0);
                    if (!empty($month) && !empty($nextMonth)) {
                        $month->setTime(0,0,0);
                        $nextMonth->setTime(0,0,0);
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
                        $query = Index::load('{site specific index}')->query();
                        $query->addCondition('status', 1);
                        $query->addCondition('field_issue_date', [$monthTimestamp, $nextMonthTimestamp], 'BETWEEN');
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
                            // Process issue dates for the facet label and raw value.
                            $pos = strpos($param, 'issue_date');
                            $pos2 = strpos($param, '-');
                            if ($pos !== false && $pos2 !== false) {
                                $value = explode(":", $param);
                                $activeItemCode = $value[1];
                            }
                        }
                        // Add the body search if the search bar has been filled out.
                        if (!empty($params['query'])) {
                            $query->addCondition('body', $params['query'], 'CONTAINS');
                        }
                        // Run the query.
                        $entities = $query->execute();
                        if ($entities->getResultCount() > 0) {
                            $url = clone reset($results)->getUrl();
                            $options = $url->getOptions();
                            $options['query']['f'][] = 'issue_date_facet:' . reset($results)->getDisplayValue();
                            $options['query']['f'][] = 'issue_date_facet:' . $activeItemFirst . '-' . $monthNumber;
                            $url->setOptions($options);
                            $results[$activeItemFirst . '-' . $monthNumber] = new Result($facet, $activeItemFirst . '-' . $monthNumber, $monthName . ' ' . $activeItemFirst, $entities->getResultCount());
                            $results[$activeItemFirst . '-' . $monthNumber]->setUrl($url);
                            if (!empty($activeItemCode) && !empty($results[$activeItemCode])) {
                                $results[$activeItemCode]->setActiveState(TRUE);
                            }
                        }
                    }
                }
                if ($this->checkDateFacetsCount($params) > 1) {
                    $this->unsetAllInactiveFacetResults($results);
                }
            }
            if (!empty($activeItemFirst) && !empty($activeItemSecond)) {
                $activeMonth = $this->getActiveMonth($params);
                kint($activeMonth);
                $daysInCurrentMonth = cal_days_in_month(CAL_GREGORIAN, $activeMonth, $activeItemFirst);
                kint([$activeMonth, $daysInCurrentMonth]);
                // Process months. PHP DateTime months start at 1. So start there.
                for ($i = 1; $i < $daysInCurrentMonth; $i++) {

                    $day = \DateTime::createFromFormat('j', $i);
                    $month = \DateTime::createFromFormat('m', $activeMonth);
                    $iPlusOne = $i + 1;
                    // Get the  current and next month Objects + Set the time to 00:00:00
                    $day = \DateTime::createFromFormat('Y-m-d', $activeItemSecond . '-' . $i);
                    $day->setTime(0,0,0);
                    $nextDay = \DateTime::createFromFormat('Y-m-d', $activeItemSecond . '-' . $iPlusOne);
                    $nextDay->setTime(0,0,0);
                    if (!empty($day) && !empty($nextDay)) {
                        $dayTimestamp = $day->getTimestamp();
                        $nextDayTimestamp = $nextDay->getTimestamp();
                        // Get the month in the n format (month number, without leading 0).
                        $dayObj = \DateTime::createFromFormat('n', $i);
                        // Human readable month.
                        $dayName = $dayObj->format('F');
                        // Month with leading 0.
                        $dayNumber = $dayObj->format('m');
                        // Create the query on the index.
                        // We need to use this index to return the result count for
                        // the newly created facets, since they aren't found automatically.
                        $query = Index::load('{Site specific index}')->query();
                        $query->addCondition('status', 1);
                        $query->addCondition('field_issue_date', [$dayTimestamp, $nextDayTimestamp], 'BETWEEN');
                        // Add the extra facet information to get the count data.
                        foreach ($params['f'] as $param) {
                            // Other facets.
                            $conditions = [
                                'type' => 'content_type',
                                'field_1' => 'field1',
                                'field_2' => 'field2',
                                'field_3' => 'field3',
                            ];
                            // Helper function that processes the conditions for the query
                            // based on the other facet results
                            $this->processConditions($param, $conditions, $query);
                            // Process issue dates for the facet label and raw value.
                            $pos = strpos($param, 'issue_date');
                            $pos2 = strpos($param, '-');
                            if ($pos !== false && $pos2 !== false) {
                                $value = explode(":", $param);
                                $activeItemCode = $value[1];
                            }
                        }
                        // Add the body search if the search bar has been filled out.
                        if (!empty($params['query'])) {
                            $query->addCondition('body', $params['query'], 'CONTAINS');
                        }
                        // Run the query.
                        $entities = $query->execute();
                        if ($entities->getResultCount() > 0) {
                            $url = clone reset($results)->getUrl();
                            $options = $url->getOptions();
                            $options['query']['f'][] = 'issue_date_facet:' . reset($results)->getDisplayValue();
                            $options['query']['f'][] = 'issue_date_facet:' . $activeItemSecond;
                            $options['query']['f'][] = 'issue_date_facet:' . $activeItemSecond . '-' . $i;
                            $url->setOptions($options);
                            $displayValue = $day->format('jS') . ' of ' . $month->format('F') . ' ' . $activeItemFirst;
                            $results[$activeItemSecond . '-' . $i] = new Result($facet, $activeItemSecond . '-' . $i, $displayValue, $entities->getResultCount());
                            $results[$activeItemSecond . '-' . $i]->setUrl($url);
                            if (!empty($activeItemCode) && !empty($results[$activeItemCode])) {
                                $results[$activeItemCode]->setActiveState(TRUE);
                            }
                        }
                    }
                }
            }
            kint($results);
        }
        return $results;
    }

    function getActiveMonth($params) {
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