CleverAge/EAVApiPlatformBundle
=======================

This bundle provides basic support for Sidus/EAVModelBundle with ApiPlatform.

When declaring any family as a resource for the Api, you need to declare a specific class for this family:

[SidusEAVModelBundle/Documentation/12-custom_classes.html](https://vincentchalnot.github.io/SidusEAVModelBundle/Documentation/12-custom_classes.html)

And for Hydra documenation to work you need to annotate your final classes with the ```@Family``` annotation.

Everything else is basic ApiPlatform implementation.

### Installation

Require Api Platform in your composer.json (with the version you need) as well as the EAV compatibility bundle if you
need to expose EAV data:

````yaml
{
    # ...
    "require": {
        # ...
        "api-platform/api-platform": "2.1.*",
        "cleverage/eav-api-platform-bundle": "1.0.*"
    }
}
````

Add the bundles to your kernel:

````php
<?php
        $projectBundles = [
            // ...
            new ApiPlatform\Core\Bridge\Symfony\Bundle\ApiPlatformBundle(),
            new CleverAge\EAVApiPlatformBundle\CleverAgeEAVApiPlatformBundle(),
        ];
````

### Filters

This bundle provides 6 different filters for you to use in resource class declaration in place of Doctrine's
ApiPlatform's ones.

 - ```CleverAge\EAVApiPlatformBundle\Filter\BooleanFilter```
 - ```CleverAge\EAVApiPlatformBundle\Filter\DateFilter```
 - ```CleverAge\EAVApiPlatformBundle\Filter\NumericFilter```
 - ```CleverAge\EAVApiPlatformBundle\Filter\OrderFilter```
 - ```CleverAge\EAVApiPlatformBundle\Filter\RangeFilter```
 - ```CleverAge\EAVApiPlatformBundle\Filter\SearchFilter```
