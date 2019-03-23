<?php

namespace Drupal\facet_granular_date\Plugin\facets\url_processor;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Url;
use Drupal\facets\FacetInterface;
use Drupal\facets\UrlProcessor\UrlProcessorPluginBase;
use Symfony\Component\HttpFoundation\Request;

/**
 * Query string URL processor.
 *
 * For later review - I wonder if it would be better to extend the existing facets plugin. Probably makes more sense.
 *
 * @FacetsUrlProcessor(
 *   id = "granular_date_query_string",
 *   label = @Translation("Granular date Query string"),
 *   description = @Translation("This processor is very similar to the default, just provides granular dates")
 * )
 */
class GranularDateQueryString extends UrlProcessorPluginBase {

    /**
     * A string of how to represent the facet in the url.
     *
     * @var string
     */
    protected $urlAlias;

    /**
     * {@inheritdoc}
     */
    public function __construct(array $configuration, $plugin_id, $plugin_definition, Request $request, EntityTypeManagerInterface $entity_type_manager) {
        parent::__construct($configuration, $plugin_id, $plugin_definition, $request, $entity_type_manager);
        $this->initializeActiveFilters();
    }

    /**
     * {@inheritdoc}
     */
    public function buildUrls(FacetInterface $facet, array $results) {
        // No results are found for this facet, so don't try to create urls.
        if (empty($results)) {
            return [];
        }

        // First get the current list of get parameters.
        $get_params = $this->request->query;

        // When adding/removing a filter the number of pages may have changed,
        // possibly resulting in an invalid page parameter.
        if ($get_params->has('page')) {
            $current_page = $get_params->get('page');
            $get_params->remove('page');
        }

        // Set the url alias from the facet object.
        $this->urlAlias = $facet->getUrlAlias();

        $request = $this->request;
        if ($facet->getFacetSource()->getPath()) {
            $request = Request::create($facet->getFacetSource()->getPath());
            $request->attributes->set('_format', $this->request->get('_format'));
        }

        // Grab any route params from the original request.
        $routeParameters = Url::createFromRequest($this->request)
            ->getRouteParameters();

        // Create a request url.
        $requestUrl = Url::createFromRequest($request);
        $requestUrl->setOption('attributes', ['rel' => 'nofollow']);

        /** @var \Drupal\facets\Result\ResultInterface[] $results */
        foreach ($results as &$result) {
            // Reset the URL for each result.
            $url = clone $requestUrl;

            // Sets the url for children.
            if ($children = $result->getChildren()) {
                $this->buildUrls($facet, $children);
            }

            $filter_string = $this->urlAlias . $this->getSeparator() . $result->getRawValue();
            $result_get_params = clone $get_params;

            $filter_params = [];
            foreach ($this->getActiveFilters() as $facet_id => $values) {
                foreach ($values as $value) {
                    $filter_params[] = $this->getUrlAliasByFacetId($facet_id, $facet->getFacetSourceId()) . ":" . $value;
                }
            }

            // If the value is active, remove the filter string from the parameters.
            if ($result->isActive()) {
                foreach ($filter_params as $key => $filter_param) {
                    if ($filter_param == $filter_string) {
                        unset($filter_params[$key]);
                    }
                }
                if ($facet->getEnableParentWhenChildGetsDisabled() && $facet->getUseHierarchy()) {
                    // Enable parent id again if exists.
                    $parent_ids = $facet->getHierarchyInstance()->getParentIds($result->getRawValue());
                    if (isset($parent_ids[0]) && $parent_ids[0]) {
                        // Get the parents children.
                        $child_ids = $facet->getHierarchyInstance()->getNestedChildIds($parent_ids[0]);

                        // Check if there are active siblings.
                        $active_sibling = FALSE;
                        if ($child_ids) {
                            foreach ($results as $result2) {
                                if ($result2->isActive() && $result2->getRawValue() != $result->getRawValue() && in_array($result2->getRawValue(), $child_ids)) {
                                    $active_sibling = TRUE;
                                    continue;
                                }
                            }
                        }
                        if (!$active_sibling) {
                            $filter_params[] = $this->urlAlias . $this->getSeparator() . $parent_ids[0];
                        }
                    }
                }
            }
            // If the value is not active, add the filter string.
            else {
                $filter_params[] = $filter_string;
                $facetProcessors = $facet->getProcessors();
                if (isset($facetProcessors['date_granular_item'])) {
                    $dateKeys = $this->processDateKeysAndCount($results);
                    if ($dateKeys['yearcount'] == 1 && sizeof($results) > 1) {
                        // [0] seems to be good enough here - could review if $field : $yearKey could be
                        // the better way to do it.
                        unset($filter_params[0]);
                    }
                    if ($dateKeys['monthcount'] == 1 && sizeof($results) > 1) {
                        // [0] seems to be good enough here - could review if $field : $monthKey could be
                        // the better way to do it.
                        unset($filter_params[0]);
                    }
                }
                if ($facet->getUseHierarchy()) {
                    // If hierarchy is active, unset parent trail and every child when
                    // building the enable-link to ensure those are not enabled anymore.
                    $parent_ids = $facet->getHierarchyInstance()->getParentIds($result->getRawValue());
                    $child_ids = $facet->getHierarchyInstance()->getNestedChildIds($result->getRawValue());
                    $parents_and_child_ids = array_merge($parent_ids, $child_ids);
                    foreach ($parents_and_child_ids as $id) {
                        $filter_params = array_diff($filter_params, [$this->urlAlias . $this->getSeparator() . $id]);
                    }
                }
                // Exclude currently active results from the filter params if we are in
                // the show_only_one_result mode.
                if ($facet->getShowOnlyOneResult()) {
                    foreach ($results as $result2) {
                        if ($result2->isActive()) {
                            $active_filter_string = $this->urlAlias . $this->getSeparator() . $result2->getRawValue();
                            foreach ($filter_params as $key2 => $filter_param2) {
                                if ($filter_param2 == $active_filter_string) {
                                    unset($filter_params[$key2]);
                                }
                            }
                        }
                    }
                }
            }
            asort($filter_params, \SORT_NATURAL);
            $result_get_params->set($this->filterKey, array_values($filter_params));
            if (!empty($routeParameters)) {
                $url->setRouteParameters($routeParameters);
            }

            if ($result_get_params->all() !== [$this->filterKey => []]) {
                $new_url_params = $result_get_params->all();

                // Facet links should be page-less.
                // See https://www.drupal.org/node/2898189.
                unset($new_url_params['page']);

                // Set the new url parameters.
                $url->setOption('query', $new_url_params);
            }
            $result->setUrl($url);
        }
        // For review - Could fold the below into the above foreach - For now it's ok here,
        // performance wise it would make sense not to loop through all twice.. What happens if you have 20,000 nodes?
        // Set the new URL patterns as the follows:
        // Click year : Take you back to search
        // Click month : Take you back to year
        // Click day : Take you back to month.
        $facetProcessors = $facet->getProcessors();
        $field = $this->getUrlAliasByFacetId($facet->id(), $facet->getFacetSourceId());
        if (isset($facetProcessors['date_granular_item'])) {
            $dateKeys = $this->processDateKeysAndCount($results);
            // Review - Clean this up as part of code review.
            if ($dateKeys['yearcount'] == 1 && sizeof($results) > 1) {
                $url = $results[$dateKeys['yearkey']]->getUrl();
                $options = $url->getOptions();
                foreach ($options['query']['f'] as $key => $option) {
                    $pos = strpos($option, $field);
                    if ($pos !== false) {
                        unset($options['query']['f'][$key]);
                    }
                }
                $url->setOptions($options);
                $results[$dateKeys['yearkey']]->setUrl($url);

            }
            if ($dateKeys['monthcount'] === 1 && sizeof($results) > 1) {
                $url = $results[$dateKeys['monthkey']]->getUrl();
                $options = $url->getOptions();
                foreach ($options['query']['f'] as $key => $option) {
                    $pos = strpos($option, 'issue_date');
                    if ($pos !== false) {
                        unset($options['query']['f'][$key]);
                    }
                }
                $options['query']['f'][] = $field . ':' . $dateKeys['yearkey'];
                $url->setOptions($options);
                $results[$dateKeys['monthkey']]->setUrl($url);
            }
            if ($dateKeys['daycount'] === 1 && sizeof($results) > 1) {
                $url = $results[$dateKeys['daykey']]->getUrl();
                $options = $url->getOptions();
                foreach ($options['query']['f'] as $key => $option) {
                    $pos = strpos($option, $field);
                    if ($pos !== false) {
                        unset($options['query']['f'][$key]);
                    }
                }
                $options['query']['f'][] = $field . ':' .  $dateKeys['monthkey'];
                $url->setOptions($options);
                $results[$dateKeys['monthkey']]->setUrl($url);
            }
        }
        // Restore page parameter again. See https://www.drupal.org/node/2726455.
        if (isset($current_page)) {
            $get_params->set('page', $current_page);
        }
        return $results;
    }

    /**
     * Initializes the active filters from the request query.
     *
     * Get all the filters that are active by checking the request query and store
     * them in activeFilters which is an array where key is the facet id and value
     * is an array of raw values.
     */
    protected function initializeActiveFilters() {
        $url_parameters = $this->request->query;

        // Get the active facet parameters.
        $active_params = $url_parameters->get($this->filterKey, [], TRUE);
        $facet_source_id = $this->configuration['facet']->getFacetSourceId();

        // When an invalid parameter is passed in the url, we can't do anything.
        if (!is_array($active_params)) {
            return;
        }

        // Explode the active params on the separator.
        foreach ($active_params as $param) {
            $explosion = explode($this->getSeparator(), $param);
            $url_alias = array_shift($explosion);
            $facet_id = $this->getFacetIdByUrlAlias($url_alias, $facet_source_id);
            $value = '';
            while (count($explosion) > 0) {
                $value .= array_shift($explosion);
                if (count($explosion) > 0) {
                    $value .= $this->getSeparator();
                }
            }
            if (!isset($this->activeFilters[$facet_id])) {
                $this->activeFilters[$facet_id] = [$value];
            }
            else {
                $this->activeFilters[$facet_id][] = $value;
            }
        }
    }

    /**
     * Gets the facet id from the url alias & facet source id.
     *
     * @param string $url_alias
     *   The url alias.
     * @param string $facet_source_id
     *   The facet source id.
     *
     * @return bool|string
     *   Either the facet id, or FALSE if that can't be loaded.
     */
    protected function getFacetIdByUrlAlias($url_alias, $facet_source_id) {
        $mapping = &drupal_static(__FUNCTION__);
        if (!isset($mapping[$facet_source_id][$url_alias])) {
            $storage = $this->entityTypeManager->getStorage('facets_facet');
            $facet = current($storage->loadByProperties(['url_alias' => $url_alias, 'facet_source_id' => $facet_source_id]));
            if (!$facet) {
                return NULL;
            }
            $mapping[$facet_source_id][$url_alias] = $facet->id();
        }
        return $mapping[$facet_source_id][$url_alias];
    }

    /**
     * Gets the url alias from the facet id & facet source id.
     *
     * @param string $facet_id
     *   The facet id.
     * @param string $facet_source_id
     *   The facet source id.
     *
     * @return bool|string
     *   Either the url alias, or FALSE if that can't be loaded.
     */
    protected function getUrlAliasByFacetId($facet_id, $facet_source_id) {
        $mapping = &drupal_static(__FUNCTION__);
        if (!isset($mapping[$facet_source_id][$facet_id])) {
            $storage = $this->entityTypeManager->getStorage('facets_facet');
            $facet = current($storage->loadByProperties(['id' => $facet_id, 'facet_source_id' => $facet_source_id]));
            if (!$facet) {
                return FALSE;
            }
            $mapping[$facet_source_id][$facet_id] = $facet->getUrlAlias();
        }
        return $mapping[$facet_source_id][$facet_id];
    }

    private function processDateKeysAndCount($results) {
        $yearCount = 0;
        $yearKey = 0;
        $monthCount = 0;
        $monthKey = 0;
        $dayCount = 0;
        $dayKey = 0;
        foreach ($results as $key => $granular_result) {
            // Find granularity based on Hyphons and log them for use later.
            $hyphonCount = substr_count($key, '-');
            switch ($hyphonCount) {
                case 0:
                    $yearKey = $key;
                    $yearCount++;
                    break;
                case 1:
                    $monthCount++;
                    $monthKey = $key;
                    break;
                case 2:
                    $dayCount++;
                    $dayKey = $key;
            }
        }
        return [
            'yearcount'   => $yearCount,
            'yearkey'     => $yearKey,
            'monthcount'  => $monthCount,
            'monthkey'    => $monthKey,
            'daycount'    => $dayCount,
            'daykey'      => $dayKey,
        ];
    }

}
