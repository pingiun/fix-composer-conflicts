# fix-composer-conflicts

Fix composer conflicts with some help.

## Installation

The easiest way to install is using `composer global require`.

```bash
composer global require pingiun/fix-composer-conflicts
```

Then you can find the tool in your composer home directory.
Use this command to find out where it is:

```bash
composer global config home
```

You could add this directory to your `PATH` variable if you want to run it easily.

Note, this tool requires PHP 8.3 or higher.

## Usage

If you are doing a merge, rebase or cherry-pick and you get a git conflict in your `composer.json` file, you can use this tool to fix it.
Run the following command in the root of your project:

```bash
fix-composer-conflicts
```

You will get an interactive prompt like this:

[![screenshot](./screenshot.png)](./screenshot.png)

If you need help with the single letter commands, you can type `?` and hit enter for help.
