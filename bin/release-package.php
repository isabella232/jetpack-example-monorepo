<?php
/**
 * Release a Jetpack package.
 *
 * Example usage: `php bin/release-package.php example-package 1.2.3`, where:
 * - `1.2.3` is the tag version
 * - `example-package` is the name of the package that corresponds to:
 *   - A Jetpack package in `/packages/example-package` in the main Jetpack repository
 *   - A repository that lives in `automattic/jetpack-example-package`.
 *
 * This will:
 * - Push a new release in the main repository, with a tag `automattic/jetpack-example-package@1.2.3`.
 * - Push the latest contents and history of the package directory to the package repository.
 * - Push a new release in the package repository, with a tag `v1.2.3`.
 *
 * @package jetpack
 */

// We need the package name to be able to mirror its directory to a package.
if ( empty( $argv[1] ) ) {
	die( 'Error: Package name has not been specified.' );
}

// Package name should contain only alphanumeric characters and dashes (example: `example-package`).
if ( ! preg_match( '/^[A-Za-z0-9\-]+$/', $argv[1] ) ) {
	die( 'Error: Package name is incorrect.' );
}
$package_name = $argv[1];

// We need the tag name (version) to be able to mirror a package to its corresponding version.
if ( empty( $argv[1] ) ) {
	die( 'Error: Tag name (version) has not been specified.' );
}

// Tag name (version) should match the format `1.2.3`.
if ( ! preg_match( '/^[0-9.]+$/', $argv[2] ) ) {
	die( 'Error: Tag name (version) is incorrect.' );
}
$tag_version = $argv[2];

// Create the new tag in the main repository.
$main_repo_tag = 'automattic/jetpack-' . $package_name . '@' . $tag_version;
$command       = sprintf(
	'git tag -a %1$s -m "%1$s"',
	escapeshellarg( $main_repo_tag )
);
execute( $command, 'Could not tag the new package version in the main repository.' );

// Push the new tag to the main repository.
$command = sprintf(
	'git push origin %s',
	escapeshellarg( $main_repo_tag )
);
execute( $command, 'Could not push the new package version tag to the main repository.' );

// Do the magic: bring the subdirectory contents (and history of non-empty commits) onto the master branch.
$command = sprintf(
	'git filter-branch -f --prune-empty --subdirectory-filter %s master',
	escapeshellarg( 'packages/' . $package_name )
);
execute( $command, 'Could not filter the branch to the package contents.' );

// Add the corresponding package repository as a remote.
$package_repo_url = sprintf(
	'https://github.com/Automattic/jetpack-%s.git',
	$package_name
);
$command          = sprintf(
	'git remote add package %s',
	escapeshellarg( $package_repo_url )
);
execute( $command, 'Could not add the new package repository remote.' );

// Push the contents to the package repository.
execute( 'git push package master --force', 'Could not push to the new package repository.' );

// Grab all the existing tags from the package repository.
execute( 'git fetch --tags', 'Could not fetch the existing tags of the package.' );

// Create the new tag.
$command = sprintf(
	'git tag -a v%1$s -m "Version %1$s"',
	escapeshellarg( $tag_version )
);
execute( $command, 'Could not tag the new version in the package repository.' );

// Push the new tag to the package repository.
$command = sprintf(
	'git push package v%s',
	escapeshellarg( $tag_version )
);
execute( $command, 'Could not push the new version tag to the package repository.' );

// Reset the main repository to the original state.
execute( 'git reset --hard refs/original/refs/heads/master', 'Could not reset the repository to its original state.' );

// Remove the temporary repository package remote.
execute( 'git remote rm package', 'Could not clean up the package repository remote.' );

/**
 * Execute a command.
 * On failure, throw an exception with the specified message (if specified).
 *
 * @throws Exception With the specified message if the command fails.
 *
 * @param string $command Command to execute.
 * @param string $error   Error message to be thrown if command fails.
 */
function execute( $command, $error = '' ) {
	// phpcs:disable WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru
	passthru( $command, $status );
	// phpcs:enable WordPress.PHP.DiscouragedPHPFunctions.system_calls_passthru

	if ( $error && 0 !== $status ) {
		throw new Exception( $error );
	}
}
