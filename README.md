

# Plugin for Drupal 8 facets that creates a Drill down date facet.

This module was built to solve the issue* where date facets are no longer drilldown in Drupal 8. 
This module provides a new Facet processor to achieve that.

*https://www.drupal.org/project/facets/issues/2802571 

E.G
- Clicking 2018 will created new facets for January 2018, Febuary 2018 etc
- Clicking January 2018 will create facets for 1st January 2018, 2nd January 2018 etc.

## Installation

For each facet source 
- Go to admin/config/search/facets/facet-sources/{source}/edit and change the URL processor to "Granular date Query string".

For your date facets 
- Go to admin/config/search/facets/{facet}/edit and change the processor to "Date item granular processor".

{Image of Years}
{Image on Months with year clicked}
{Image of Days with month clicked.

## Configuration

This module is now ready for testing in pre production. I've tested it on two different sites and it's
worked well. I would love some more testing done on it, 
especially in environments with 5,000+ indexed nodes. 

## Caveats:

As of alpha1 - This may not work with other traditional date facets. It may be one or the other. I'm planning on running more tests for this in the future.
This module provides a custom URL processor - So if you already have a custom URL processor this won't be compatible for this facet source. 

## Work to do:

- More code clean up - Marked by TODOs in the code.
- Refactor the Queries
- If anyone is interesting in submitting patchs or PRs I would be happy to review them. My main issue is the Querys. They need refactoring
- Add tests.


