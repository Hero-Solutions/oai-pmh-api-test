# OAI-PMH API test application

## Summary

This is a simple test application to harvest metadata from an [OAI-PMH](https://www.openarchives.org/pmh/) API, more specifically the [Datahub](https://github.com/thedatahub/Datahub). It makes use of the PHP library [caseyamcl/phpaoipmh](https://github.com/caseyamcl/phpoaipmh).

Sample code is found inside ```src/Command/TestCommand.php```. Configuration of the API URL and the XPath expressions to find relevant XML data is found in ```config/oai_pmh_api.yaml```.

## Requirements

- PHP >= 7.2.5
- Composer >= 2.0

## Installation

Clone this repository
```
git clone git@github.com:Hero-Solutions/oai-pmh-api-test.git
```

Install through composer:
```
composer install
```

## Usage

You can run the test application through the following command:
```
php bin/console app:test
```

This will output the metadata of the first 5 records.
