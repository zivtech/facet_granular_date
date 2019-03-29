<?php

namespace Drupal\facet_granular_date\Plugin\facets\processor;

use Drupal\facets\FacetInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\facets\Processor\BuildProcessorInterface;
use Drupal\facets\Processor\ProcessorPluginBase;
use Drupal\facet_granular_date\Plugin\facets\query_type\SearchApiDateGranular;
use Drupal\search_api\Entity\Index;
use Drupal\facets\Result\Result;

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
            // Hyphons determine the format -
            // 0 - Year, 1 - Month, 2 - Day.
            $hyphonCount = substr_count($activeItem, '-');
            switch($hyphonCount) {
                case 0:
                    $granularity = 'year';
                    // Create the active item facet.
                    $this->createActiveFacet($facet, $activeItem, 'year', $results);
                    // Create the new facets for filtering.
                    $this->createFacets($facet, $params, $activeItem, $granularity, $results);
                    break;
                case 1:
                    $granularity = 'month';
                    // Create the active item facets.
                    $this->createActiveFacet($facet, $activeItem, 'year', $results);
                    $this->createActiveFacet($facet, $activeItem, 'month', $results);
                    // Create the new facets for filtering.
                    $this->createFacets($facet, $params, $activeItem, $granularity, $results);
                    break;
                case 2:
                    // Day. Doesn't need a granularity, it's the ending point right now.
                    // Create the active item facets.
                    $this->createActiveFacet($facet, $activeItem, 'year', $results);
                    $this->createActiveFacet($facet, $activeItem, 'month', $results);
                    $this->createActiveFacet($facet, $activeItem, 'day', $results);
                    break;

            }
        }
        return $results;
    }

    /**
     * Helper function.
     *
     * Create an active facet for the granularity passed.
     *
     * @param $facet
     * @param $activeItem
     * @param $granularity
     * @param $results
     */
    private function createActiveFacet($facet, $activeItem, $granularity, &$results) {
        $explodedActiveItem = $this->explodeActiveItem($activeItem);
        switch($granularity) {
            case 'year':
                // Create Year facet - count gets added later
                $results[$explodedActiveItem['year']] = new Result($facet, $explodedActiveItem['year'], $explodedActiveItem['year'], 0);
                $results[$explodedActiveItem['year']]->setActiveState(TRUE);
                // Create month facet - count gets added later.
                //$monthDisplay = \DateTime::createFromFormat('Y-m', $activeItem)->format('F Y');
                //$results[$activeItem] = new Result($facet, $activeItem, $monthDisplay, 0);
                break;
            case 'month':
                $yearMonth = $explodedActiveItem['year'] . '-' . $explodedActiveItem['month'];
                // Create month facet - count gets added later.
                $monthDisplay = \DateTime::createFromFormat('Y-m', $yearMonth)->format('F Y');
                $results[$yearMonth] = new Result($facet, $yearMonth, $monthDisplay, 0);
                $results[$yearMonth]->setActiveState(TRUE);
                break;
            case 'day':
                $yearMonthDay = $explodedActiveItem['year'] . '-' . $explodedActiveItem['month'] . '-' . $explodedActiveItem['day'];
                // Create day facet - count gets added later.
                $dayDisplay = \DateTime::createFromFormat('Y-m-d', $yearMonthDay)->format('jS  \o\f F Y');
                $results[$yearMonthDay] = new Result($facet, $yearMonthDay, $dayDisplay, 0);
                $results[$yearMonthDay]->setActiveState(TRUE);
                break;
        }
    }

    /**
     * Helper function.
     *
     * Explode the active item into usable keys.
     *
     * @param $activeItem
     * @return array
     */
    private function explodeActiveItem($activeItem) {
        $explodedActiveItem = explode('-', $activeItem);
        return [
            'year' => (!empty($explodedActiveItem[0])) ? $explodedActiveItem[0] : NULL,
            'month' => (!empty($explodedActiveItem[1])) ? $explodedActiveItem[1] : NULL,
            'day' => (!empty($explodedActiveItem[2])) ? $explodedActiveItem[2] : NULL
        ];
    }

    /**
     * Helper function.
     *
     * Create all the new facet results.
     *
     * @param $facet
     * @param $params
     * @param $activeItems
     * @param $granularity
     * @param $results
     */
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


                        // Get the index ID from the facet. I think this should
                        // be safe without an !empty check since a facet
                        // always has to have an Index.
                        // TODO create helper of this.
                        // TODO - I think we can replace this with a search API query like I've done in the
                        // query type.
                        $indexId = $facet->getFacetSource()
                            ->getIndex()
                            ->id();
                        $query = Index::load($indexId)->query();
                        $field = $facet->getFieldIdentifier();
                        $query->addCondition('status', 1);
                        $query->addCondition($field, [$monthTimestamp, $nextMonthTimestamp], 'BETWEEN');
                        // Run the query.
                        $entities = $query->execute();
                        if (!empty($results) && $entities->getResultCount() > 0) {
                            $results[$activeItems . '-' . $monthNumber] = new Result($facet, $activeItems . '-' . $monthNumber, $monthName . ' ' . $activeItems, $entities->getResultCount());
                        }
                    }
                }
                break;
            case 'month':
                $explodedActiveItem = $this->explodeActiveItem($activeItems);
                $activeMonth = $explodedActiveItem['month'];
                $daysInCurrentMonth = cal_days_in_month(CAL_GREGORIAN, $activeMonth, $explodedActiveItem['year']);
                // Process days. PHP DateTime days start at 1. So start there.
                for ($i = 1; $i < $daysInCurrentMonth; $i++) {
                    $month = \DateTime::createFromFormat('m', $activeMonth);
                    $iPlusOne = $i + 1;
                    // Get the  current and next month Objects + Set the time to 00:00:00
                    $day = \DateTime::createFromFormat('Y-m-d', $activeItems . '-' . $i);
                    $day->setTime(0, 0, 0);
                    $nextDay = \DateTime::createFromFormat('Y-m-d', $activeItems . '-' . $iPlusOne);
                    $nextDay->setTime(0, 0, 0);
                    if (!empty($day) && !empty($nextDay)) {
                        $dayTimestamp = $day->getTimestamp();
                        $nextDayTimestamp = $nextDay->getTimestamp();
                        // TODO - Code duplication. We need to extract this to a helper function now...
                        // TODO - I think we can replace this with a search API query like I've done in the
                        // query type.
                        $indexId = $facet->getFacetSource()
                            ->getIndex()
                            ->id();
                        $dayQuery = Index::load($indexId)->query();
                        $dayField = $facet->getFieldIdentifier();
                        $dayQuery->addCondition('status', 1);
                        $dayQuery->addCondition($dayField, [$dayTimestamp, $nextDayTimestamp], 'BETWEEN');
                        // Run the query.
                        $dayEntities = $dayQuery->execute();

                        if ($dayEntities->getResultCount() > 0) {
                            $explodedActiveItem = $this->explodeActiveItem($activeItems);
                            $displayValue = $day->format('jS') . ' of ' . $month->format('F') . ' ' . $explodedActiveItem['year'];
                            $results[$activeItems . '-' . $day->format('d')] = new Result($facet, $activeItems . '-' . $day->format('d'), $displayValue, $dayEntities->getResultCount());
                        }
                    }
                }
                break;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function buildConfigurationForm(array $form, FormStateInterface $form_state, FacetInterface $facet) {
        return [];
    }

    /**
     * {@inheritdoc}
     *  TODO - Can we change this to not mess with other date query types?
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