# Installation

in distribution:

composer require cweagans/composer-patches:dev-main
composer update
composer patches-relock
# to ensure we start from scratch
rm -Rf vendor/typo3
composer update


```
	"repositories": {
        "cr-dbal":  {
          "type": "git",
          "url": "https://github.com/skurfuerst/contentrepository-dbal"
        },
      "cr-eventstore":  {
        "type": "git",
        "url": "https://github.com/skurfuerst/eventstore-doctrineadapter"
      },
      "cr-doctrinedbaladapter":  {
        "type": "git",
        "url": "https://github.com/skurfuerst/contentgraph-doctrinedbaladapter"
      }
	},

```