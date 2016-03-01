# Elasticommerce Search Form
## Description
This plugin is that WooComerce product search replace WordPress DB with Elasticsearch.

## How to use
- Upload elasticommerce-search-form to the /wp-content/plugins/ directory.
- Move `/wp-content/plugins/elasticommerce-search-form` and run `composer install`.
- Activate plugin elasticommerce-search-form.
- Input Elasticsearch Endpoint on `Settings > Woocommerce Elasticsearch` below.

<img src="https://raw.githubusercontent.com/megumiteam/elasticommerce-search-form/master/screenshot-1.png" title="setting screen"/>

### Support WP-CLI
Set up Elasticsearch below.

    wp elasticsearch setup --host=example.com --port=9200
