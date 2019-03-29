Plunin for Drupal 8 facets that creates a Drill down date facet.

E.G. 
Clicking 2018 will created new facets for January 2018, Febuary 2018 etc
Clicking January 2018 will create facets for 1st January 2018, 2nd January 2018 etc.

See https://www.drupal.org/project/facets/issues/2802571

This module was built to solve the issue above where date facets are no longer drilldown in Drupal 8. 
This module provides a new Facet processor to achieve that. See the image below for how it works.

{Image of Years}
{Image on Months with year clicked}
{Image of Days with month clicked.

Configuration reguired:

{Images of config, facet page and facet source page.}

This module is now ready for testing in pre production. I've tested it on two different sites and it's
worked well. I would love some more testing done on it, 
especially in environments with 5,000+ indexed nodes. 

Work to do:
- More code clean up - Still a few things that could be done better, far more efficient. Marked by TODOs in the code.
If anyone is interesting in submitting patchs or PRs I would be happy to review them.
- Add tests.


