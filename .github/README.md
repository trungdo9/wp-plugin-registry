# GitHub Actions Workflows

This directory contains example GitHub Actions workflows for your WordPress plugins.

## Usage

Copy one of these files to your WordPress plugin repository's `.github/workflows/` directory.

## Files

### `wordpress-plugin.yml`
Complete example with:
- Build and deploy job
- Slack notifications
- Version bumping

### `simple-example.yml`
Minimal example for basic use cases.

## Setup

1. Copy a workflow file to `.github/workflows/` in your plugin repository
2. Customize the commands for your plugin
3. Commit and push
4. The workflow will be available for manual triggering via GitHub Actions UI

## Manual Trigger

After pushing to GitHub, you can manually trigger the workflow:

1. Go to your repository on GitHub
2. Click "Actions" tab
3. Select the workflow
4. Click "Run workflow"

## WP Plugin Registry Integration

When GitHub Actions is enabled in WP Plugin Registry:
- Events like install/update will automatically trigger this workflow
- Workflow inputs are passed from the plugin
