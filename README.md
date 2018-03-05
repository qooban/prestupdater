# prestupdater
PHP script to be executed using Prestashop Cron Task manager

## Usage
- Add script to internet-accessible place on your Prestashop server
- Add cron line (usinge eg [cronjobs](https://github.com/PrestaShop/cronjobs) module) to execute `https://<URL>?secure_key=<SECURE_KEY>`, where
  - URL - address to `sync_products_attributes.php` file
  - SECURE_KEY - MD5 of `PS_SHOP_NAME` configuration parameter value
