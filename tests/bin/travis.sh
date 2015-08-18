#!/usr/bin/env bash
# usage: travis.sh before|after

if [ $1 == 'before' ]; then

	# place a copy of woocommerce where the unit tests etc. expect it to be
	mkdir -p "../woocommerce"
	curl -L https://api.github.com/repos/woothemes/woocommerce/tarball/$WC_VERSION?access_token=$GITHUB_TOKEN | tar --strip-components=1 -zx -C "../woocommerce"

	# place a copy of woocommerce subscriptions where the unit tests etc. expect it to be - needs to be a repo with the test dir
	git clone https://$GITHUB_TOKEN@github.com/Prospress/woocommerce-subscriptions.git "../woocommerce-subscriptions" -b $WCS_VERSION

fi