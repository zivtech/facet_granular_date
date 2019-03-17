<?php

namespace Drupal\facet_granular_date\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Event;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;


/**
 * Class initSubscriber.
 *
 * This is used in conjuction with the newly created facet date granualar
 * processor.
 * Redirects to search in the following scenario - With 2018 as an example.
 *
 * Click 2018
 * Click March 2018
 * Click 2018.
 *
 * This behaviour will cause issues without this since the second 2018 click
 * is removing that filter, which keeping the Month one.
 */
class initSubscriber implements EventSubscriberInterface {


    /**
     * Constructs a new initSubscriber object.
     */
    public function __construct() {

    }

    /**
     * {@inheritdoc}
     */
    static function getSubscribedEvents() {
        $events['kernel.request'] = ['initEvent'];

        return $events;
    }

    /**
     * This method is called whenever the initEvent event is
     * dispatched.
     *
     * TODO - This needs refactoing. It's using the issue date (A specific
     * field for the site this was builts for.
     *
     * Needs to get all the facets in the params and check them properly
     * without site specific shortcuts.
     *
     * @param GetResponseEvent $event
     * @return $event
     */
    public function initEvent(Event $event) {

        $eventRequest = $event->getRequest();

        //kint($eventRequest);die();
        //$clonedEvent->query->remove('f');
        /*$response = new RedirectResponse();
        $event->setResponse($response);*/
        $routeName = \Drupal::routeMatch()->getRouteName();
        //$path = \Drupal\Core\Url::fromRoute($routeName, $clonedEvent->query->all());
        //kint ([$path->toString(), $clonedEvent,$clonedEvent->getQueryString()]); die();
        $params = $event->getRequest()->query->all();
        $pathinfo = $event->getRequest()->getPathInfo();
        $yearRemoved = false;
        // Keep track of how many issue date facets have been found in the
        // request params.
        $issueDateFacets = 0;
        if (!empty($params['f'])) {
            $yearParam = false;
            $monthParam = false;
            $dayParam = false;
            foreach ($params['f'] as $param) {
                $pos = strpos($param, 'issue_date');
                if($pos !== false) {
                    $hyphonCount = substr_count($param, '-');
                    // TODO - For cleanliness, lets make this a private function later.
                    switch($hyphonCount) {
                        case 0:
                            $yearParam = $param;
                            break;
                        case 1:
                            $monthParam = $param;
                            break;
                        case 2:
                            $dayParam = $param;
                            break;

                    }
                    $issueDateFacets++;
                }
                $pos2 = strpos($param, '-');
                if ($pos !== false && $pos2 !== false) {
                    // The
                    $yearRemoved = true;
                }
            }
            $addUrlParams = '';
            // TODO - Maybe private function this too.
            // TODO - Look at changing this evnent to PostRequest or whatever it's called.
            // TODO - Up to this part. Needs some more love.. Starting to work but getting a little out of hand.
            if (empty($yearParam)) {
                $monthParam = false;
                $dayParam = false;
                $eventRequest->query->remove('f');
                /*$facetParams = $eventRequest->query->get('f');
                unset($facetParams[1]);
                unset($facetParams[2]);
                $eventRequest->query->set('f', $facetParams);*/
            }
            if (empty($monthParam)) {
                $dayParam = false;
                $facetParams = $eventRequest->query->get('f');
                // TODO I think 2 here is wrong. kint die to check.
                unset($facetParams[1]);
                $eventRequest->query->set('f', $facetParams);
            }
            //kint($yearParam); die();
            //TODO - Must be a better way of doing this.
            $addUrlParams .= ($yearParam) ? rawurlencode('f[0]') . '=' . rawurlencode($yearParam) . '&' : '';
            $addUrlParams .= ($monthParam) ? rawurlencode('f[1]') . '=' . rawurlencode($monthParam) . '&' : '';
            $addUrlParams .= ($dayParam) ? rawurlencode('f[2]') . '=' . rawurlencode($dayParam) . '&' : '';
            $addUrlParams = rtrim($addUrlParams, "&");
            $addUrlParams = '?' . $addUrlParams;

            $routeName = \Drupal::routeMatch()->getRouteName();
            $path = \Drupal\Core\Url::fromRoute($routeName, $eventRequest->query->all())->toString();

            //kint([$event->getRequest()->getRequestUri(),  '/search' . $addUrlParams]); die();
            //kint([$event->getRequest()->getRequestUri(), $path]);die();
            // TODO can't use /search. Too site specific. Get the request path instead.
            if ($event->getRequest()->getRequestUri() != $path) {
            //if ($event->getRequest()->getRequestUri() != '/search' . $addUrlParams) {
                // TODO can't use /search. Too site specific. Get the request path instead.
                //$response = new RedirectResponse('/search' . $addUrlParams, 302);
                $response = new RedirectResponse($path, 302);
                $event->setResponse($response);
                return $event;
            }

            // TODO can't use /search. Too site specific. Get the request path instead.
            /*if (!empty($params['f']) && $pathinfo == '/search' && $yearRemoved && $issueDateFacets == 1) {
                $addUrlParams = '';
                foreach ($params['f'] as $key => $param) {
                    $pos = strpos($param, 'issue_date');
                    if($pos === false) {
                        $addUrlParams .= rawurlencode('f[' . $key . '])') . '=' . rawurlencode($param) . '&';
                    }
                }
                $addUrlParams = rtrim($addUrlParams, "&");
                $addUrlParams = '?' . $addUrlParams;
                // TODO can't use /search. Too site specific. Get the request path instead.
                $response = new RedirectResponse('/search' . $addUrlParams, 302);
                $event->setResponse($response);
                return $event;
            }*/
        }
        return $event;
    }
}
