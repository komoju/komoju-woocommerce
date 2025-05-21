#!/bin/bash

# This is copy/pasted from the 10up/action-wordpress-plugin-deploy action we
# use to deploy the plugin. All destructive actions have been removed - just
# the plugin zip file is generated.

# Note that this does not use pipefail
# because if the grep later doesn't match any deleted files,
# which is likely the majority case,
# it does not exit with a 0, and I only care about the final exit.
set -eo

SLUG=komoju-japanese-payments
echo "ℹ︎ SLUG is $SLUG"

VERSION=0.0.0
echo "ℹ︎ VERSION is $VERSION"

if [[ -z "$ASSETS_DIR" ]]; then
	ASSETS_DIR=".wordpress-org"
fi
echo "ℹ︎ ASSETS_DIR is $ASSETS_DIR"

if [[ -z "$BUILD_DIR" ]] || [[ $BUILD_DIR == "./" ]]; then
	BUILD_DIR=false
elif [[ $BUILD_DIR == ./* ]]; then 
	BUILD_DIR=${BUILD_DIR:2}
fi

if [[ "$BUILD_DIR" != false ]]; then
	if [[ $BUILD_DIR != /* ]]; then 
		BUILD_DIR="${PWD%/}/${BUILD_DIR%/}"
	fi
	echo "ℹ︎ BUILD_DIR is $BUILD_DIR"
fi

GIT_DIR="${PWD}"

SVN_URL="https://plugins.svn.wordpress.org/${SLUG}/"
SVN_DIR="${PWD}/.svn-${SLUG}"

# Checkout just trunk and assets for efficiency
# Tagging will be handled on the SVN level
echo "➤ Checking out .org repository..."
svn checkout --depth immediates "$SVN_URL" "$SVN_DIR"
cd "$SVN_DIR"
svn update --set-depth infinity assets
svn update --set-depth infinity trunk


if [[ "$BUILD_DIR" = false ]]; then
	echo "➤ Copying files..."
	if [[ -e "$PWD/.distignore" ]]; then
		echo "ℹ︎ Using .distignore"
		# Copy from current branch to /trunk, excluding dotorg assets
		# The --delete flag will delete anything in destination that no longer exists in source
		rsync -rc --exclude-from="$PWD/.distignore" "$PWD/" trunk/ --delete --delete-excluded
	else
		echo "ℹ︎ Using .gitattributes"

		cd "$GIT_DIR"

		# "Export" a cleaned copy to a temp directory
		TMP_DIR="/tmp/komoju-woocommerce-tmp"
                rm -rf "$TMP_DIR"
		mkdir "$TMP_DIR"

		# This will exclude everything in the .gitattributes file with the export-ignore flag
		git archive HEAD | tar x --directory="$TMP_DIR"

		cd "$SVN_DIR"

		# Copy from clean copy to /trunk, excluding dotorg assets
		# The --delete flag will delete anything in destination that no longer exists in source
		rsync -rc "$TMP_DIR/" trunk/ --delete --delete-excluded
	fi
else
	echo "ℹ︎ Copying files from build directory..."
	rsync -rc "$BUILD_DIR/" trunk/ --delete --delete-excluded
fi

# Copy dotorg assets to /assets
if [[ -d "$PWD/$ASSETS_DIR/" ]]; then
	rsync -rc "$PWD/$ASSETS_DIR/" assets/ --delete
else
	echo "ℹ︎ No assets directory found; skipping asset copy"
fi

# Add everything and commit to SVN
# The force flag ensures we recurse into subdirectories even if they are already added
# Suppress stdout in favor of svn status later for readability
echo "➤ Preparing files..."
svn add . --force > /dev/null

# SVN delete all deleted files
# Also suppress stdout here
svn status | grep '^\!' | sed 's/! *//' | xargs -I% svn rm %@ > /dev/null

# Copy tag locally to make this a single commit
echo "➤ Copying tag..."
svn cp "trunk" "tags/$VERSION"

# Fix screenshots getting force downloaded when clicking them
# https://developer.wordpress.org/plugins/wordpress-org/plugin-assets/
if test -d "$SVN_DIR/assets" && test -n "$(find "$SVN_DIR/assets" -maxdepth 1 -name "*.png" -print -quit)"; then
    svn propset svn:mime-type "image/png" "$SVN_DIR/assets/*.png" || true
fi
if test -d "$SVN_DIR/assets" && test -n "$(find "$SVN_DIR/assets" -maxdepth 1 -name "*.jpg" -print -quit)"; then
    svn propset svn:mime-type "image/jpeg" "$SVN_DIR/assets/*.jpg" || true
fi
if test -d "$SVN_DIR/assets" && test -n "$(find "$SVN_DIR/assets" -maxdepth 1 -name "*.gif" -print -quit)"; then
    svn propset svn:mime-type "image/gif" "$SVN_DIR/assets/*.gif" || true
fi
if test -d "$SVN_DIR/assets" && test -n "$(find "$SVN_DIR/assets" -maxdepth 1 -name "*.svg" -print -quit)"; then
    svn propset svn:mime-type "image/svg+xml" "$SVN_DIR/assets/*.svg" || true
fi

#Resolves => SVN commit failed: Directory out of date
svn update

svn status

# echo "➤ Committing files..."
# svn commit -m "Update to version $VERSION from GitHub" --no-auth-cache --non-interactive  --username "$SVN_USERNAME" --password "$SVN_PASSWORD"

if $INPUT_GENERATE_ZIP; then
  echo "Generating zip file..."
  cd "$SVN_DIR/trunk" || exit
  zip -r "${PWD}/${SLUG}.zip" .
  echo "zip-path=${PWD}/${SLUG}.zip" >> $GITHUB_OUTPUT
  cp "${PWD}/${SLUG}.zip" $GIT_DIR
  echo "✓ Zip file generated!"
fi
