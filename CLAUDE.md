# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

This is the A/B Tests Drupal module, a flexible and extensible system for running A/B tests on Drupal content. The module uses a pluggable architecture with two core concepts:

- **Deciders**: Determine which variant (view mode or block configuration) to show to users
- **Trackers**: Manage reporting of which variants were shown and how users interact with them

## Architecture

### Main Module Structure
- `src/` - Core interfaces, plugin managers, and base classes
- `js/` - JavaScript classes for client-side A/B testing functionality
- `tests/` - PHPUnit functional and JavaScript tests
- `modules/` - Sub-modules extending functionality

### Key Sub-modules
- `ab_blocks/` - Enables A/B testing on individual blocks via Layout Builder
- `ab_analytics_tracker_example/` - Example implementation of custom analytics tracker
- `ab_variant_decider_view_mode_timeout/` - Time-based view mode variant decider

### Plugin System
The module uses Drupal's plugin system with two main plugin types:
- **AbVariantDecider** (`src/Plugin/AbVariantDecider/`) - Decision logic plugins
- **AbAnalytics** (`src/Plugin/AbAnalytics/`) - Analytics tracking plugins
## Development Principles

- Favor a more functional programming style: use `array_map`, `array_filter`, `array_reduce`, ... over other types of loops (like foreach)
- Favor polymorphism over conditional cases. When possible use the factory design pattern, and subclasses (using a strategy design pattern), over if...elsif...else or switch structures.

## Development Commands

### Testing
Run PHPUnit tests from the Drupal root:
```bash
# All tests for the module
ddev php vendor/bin/phpunit web/modules/contrib/ab_tests/tests/

# Functional tests only
ddev php vendor/bin/phpunit web/modules/contrib/ab_tests/tests/src/Functional/

# JavaScript tests
ddev php vendor/bin/phpunit web/modules/contrib/ab_tests/tests/src/FunctionalJavascript/
```

### Code Quality
This is a Drupal module, so use standard Drupal development tools:

#### PHP Code Quality
```bash
# PHP CodeSniffer (from Drupal root)
ddev php vendor/bin/phpcs --standard=Drupal,DrupalPractice web/modules/contrib/ab_tests/

# PHP Code Beautifier (from Drupal root)
ddev php vendor/bin/phpcbf --standard=Drupal,DrupalPractice web/modules/contrib/ab_tests/
```

#### JavaScript Code Quality
```bash
# Install dependencies (run once)
cd web/modules/contrib/ab_tests && npm install

# Lint JavaScript files
npm run lint

# Fix linting issues automatically
npm run lint:fix

# Format JavaScript files with Prettier
npm run format

# Check if files are properly formatted
npm run format:check

# Run all JavaScript quality checks
npm run test:js
```

**JavaScript Linting Rules:**
- Follows Drupal core ESLint configuration (airbnb-base + prettier)
- Enforces consistent formatting with Prettier
- Includes module-specific globals for A/B testing classes
- Allows console methods for debugging (`console.debug`, `console.error`, etc.)
- Warns on unused variables and nested callbacks

### GitHub Actions Integration

The module includes automated JavaScript quality checks via GitHub Actions:

#### **Workflow: `.github/workflows/javascript.yml`**

**Two-job workflow:**
1. **Quality Check**: Fast-fail check that runs `npm run js:check` on all pushes/PRs
2. **PR Review**: Enhanced review with inline suggestions (PRs only)

**Key Features:**
- **ESLint Suggestion Action**: Provides inline GitHub suggestions for auto-fixable issues
- **Prettier Comments**: Automated PR comments for formatting issues
- **Targeted Feedback**: Only comments on changed lines (no noise from existing code)
- **One-click Fixes**: GitHub suggestions allow accepting fixes directly in PR review

**Running locally before push:**
```bash
# Check for issues
npm run js:check

# Auto-fix issues
npm run js:fix
```

**GitHub Actions behavior:**
- ✅ Pipeline passes: No ESLint errors, proper Prettier formatting
- ❌ Pipeline fails: ESLint errors or Prettier formatting issues
- 💬 PR Comments: Detailed guidance on fixing issues
- 🔧 Suggestions: Inline GitHub suggestions for auto-fixable ESLint issues

### Module Management
```bash
# Enable the module
drush en ab_tests

# Enable sub-modules
drush en ab_blocks ab_analytics_tracker_example

# Clear cache after changes
drush cr
```

## Key Implementation Details

### Client-Side Architecture
The A/B Blocks sub-module uses a sophisticated client-side approach:
1. Blocks are initially rendered server-side with hidden loading state
2. JavaScript determines variant using configured decider plugins
3. Ajax re-renders blocks with variant-specific configuration
4. Analytics tracker fires to record decisions

### Context Preservation
Block testing preserves page context (especially entity contexts) by serializing context during initial placement and rehydrating during Ajax requests.

### Plugin Development
- Decider plugins extend `AbVariantDeciderPluginBase` and implement `AbVariantDeciderInterface`
- Analytics plugins extend `AbAnalyticsPluginBase` and implement `AbAnalyticsInterface`
- All plugins use Drupal annotations for discovery

### Configuration Management
The module supports excluding A/B test configurations from config export/import via admin settings, useful for environment-specific testing.

## File Locations

- Core plugin interfaces: `src/AbVariantDeciderInterface.php`, `src/AbAnalyticsInterface.php`
- Plugin managers: `src/AbVariantDeciderPluginManager.php`, `src/AbAnalyticsPluginManager.php`
- Main controller: `src/Controller/AbTestsController.php`
- Block testing controller: `modules/ab_blocks/src/Controller/AjaxBlockRender.php`
- Test base classes: `tests/src/AbTestsTestBaseTrait.php`, `tests/src/Functional/AbTestsFunctionalTestBase.php`

## Commit Message Guidelines

This project follows [Conventional Commits](https://www.conventionalcommits.org) specification for commit messages.

### Format

```
<type>[optional scope]: <description>

[optional body]

[optional footer(s)]
```

### Types

- **feat**: A new feature
- **fix**: A bug fix
- **docs**: Documentation only changes
- **style**: Changes that do not affect the meaning of the code (white-space, formatting, missing semi-colons, etc)
- **refactor**: A code change that neither fixes a bug nor adds a feature
- **perf**: A code change that improves performance
- **test**: Adding missing tests or correcting existing tests
- **chore**: Changes to the build process or auxiliary tools and libraries such as documentation generation
- **ci**: Changes to CI configuration files and scripts
- **build**: Changes that affect the build system or external dependencies

### Scope (Optional)

Scope provides additional contextual information:
- **blocks**: Changes to ab_blocks sub-module
- **analytics**: Changes to analytics tracking system
- **deciders**: Changes to variant decider plugins
- **js**: JavaScript-related changes
- **workflow**: GitHub Actions workflow changes
- **deps**: Dependency updates

### Rules

1. **Type and description are mandatory**
2. **Use present tense** ("add feature" not "added feature")
3. **Use imperative mood** ("move cursor to..." not "moves cursor to...")
4. **Don't capitalize first letter** of description
5. **No period at the end** of description
6. **Body and footer are optional** but recommended for complex changes
7. **Breaking changes** must be indicated with `!` after type/scope or in footer with `BREAKING CHANGE:`

### Examples

```
feat(blocks): add support for hiding blocks in variants
fix(js): resolve race condition in decision handler initialization
docs: update plugin development guidelines
ci(workflow): enhance error handling in PHP test workflow
chore(deps): update drupal/coder to ^8.3.0
feat!: remove deprecated analytics API
```

### Breaking Changes

For breaking changes, use either:
- `feat!: remove deprecated analytics API` 
- Footer format:
  ```
  feat(analytics): redesign tracker interface
  
  BREAKING CHANGE: Analytics plugins must now implement the new TrackerInterface
  ```

## Technical Execution Flow

This section details the complete execution flow for both A/B testing features, covering server-side initialization, JavaScript decision-making, Ajax re-rendering, and analytics tracking.

### View Mode Testing (ab_view_modes) Flow

#### 1. Server-Side Initialization

**Entry Points:**
- `src/Hook/AbTestsHooks.php::entityViewModeAlter()` (line 85) - Determines initial view mode
- `src/Hook/AbTestsHooks.php::entityViewAlter()` (line 161) - Attaches A/B testing assets

**Process:**
1. **View Mode Selection**: `entityViewModeAlter()` checks if this is the full page entity and if A/B testing is active. If so, it sets the view mode to the configured default from `settings['default']['display_mode']`.

2. **Asset Attachment**: `entityViewAlter()` attaches the decider JavaScript library and adds crucial data attributes:
   - `data-ab-tests-instance-id="{entity-uuid}"` - Entity identifier for Ajax requests
   - `data-ab-tests-feature="ab_view_modes"` - Feature type identifier
   - `data-ab-tests-decider-status="idle"` - Initial status for JavaScript
   - `class="ab-test-loading"` - Initial loading state

3. **Settings**: Drupal settings are populated with:
   - Debug mode status
   - Default decision value (current view mode)
   - Decider configuration

#### 2. JavaScript Decision Process

**Entry Point:** `js/ab-tests-init.js` - Initializes the A/B testing system on page load

**Flow:**
1. **Manager Initialization**: `AbTestsManager.initialize()` scans DOM for `[data-ab-tests-feature]` elements
2. **Handler Creation**: `DecisionHandlerFactory.create()` returns `ViewModeDecisionHandler` for view mode features
3. **Decider Execution**: The configured decider plugin (e.g., `TimeoutDecider`) makes the variant decision
4. **Decision Processing**: `ViewModeDecisionHandler.handleDecision()` processes the result

#### 3. Variant Loading and Re-rendering

**Decision Handler:** `js/ViewModeDecisionHandler.js`

**Process:**
1. **Decision Validation**: Checks if the decision changes anything by comparing with `defaultDecisionValue`
2. **Security Validation**: Validates UUID format and display mode format to prevent XSS
3. **Ajax Request**: Makes GET request to `/ab-tests/render/{uuid}/{display_mode}`
4. **Server Processing**: `src/Controller/AbTestsController.php::renderVariant()` handles the request:
   - Loads entity by UUID
   - Renders with the specified view mode
   - Returns Ajax response with updated HTML

**Key Code Locations:**
- Request URL construction: `ViewModeDecisionHandler.js:36`
- Security validation: `ViewModeDecisionHandler.js:18-31`
- Server handler: `src/Controller/AbTestsController.php`

#### 4. Analytics Tracking

**Trigger:** After successful variant loading, analytics trackers are instantiated and executed

**Process:**
1. **Tracker Attachment**: During Ajax re-render, `entityViewAlter()` attaches the analytics library
2. **Tracking Execution**: JavaScript tracker receives the decision object and DOM element
3. **Event Setup**: Trackers typically set up interaction listeners (clicks, form submissions, etc.)

#### 5. Error Handling

**Client-Side Errors:**
- Invalid UUID/display mode: Throws error and prevents Ajax request
- Ajax failures: Sets `status="error"` and logs debug information
- Decider failures: Falls back to showing default variant

**Server-Side Errors:**
- Entity not found: Returns empty response
- Access denied: Respects Drupal permissions
- Render failures: Caught and logged

### Block Testing (ab_blocks) Flow

#### 1. Server-Side Initialization

**Entry Points:**
- `modules/ab_blocks/src/EventSubscriber/TestableBlockComponentRenderArray.php` - Modifies block render arrays
- Block configuration forms in Layout Builder interface

**Process:**
1. **Block Enhancement**: The event subscriber adds A/B testing metadata to blocks that have testing configured:
   - `data-ab-tests-instance-id="{placement-id}"` - Unique block identifier
   - `data-ab-tests-feature="ab_blocks"` - Feature type
   - `data-ab-tests-decider-status="idle"` - Initial status
   - Block configuration and context serialization in drupalSettings

2. **Context Serialization**: Block contexts (entities, primitives) are serialized for later Ajax reconstruction:
   ```php
   'contexts' => [
     'entity:node' => 'entity:node=123',
     'layout_builder_route_contexts' => 'string="value"'
   ]
   ```

#### 2. JavaScript Decision Process

**Handler:** `modules/ab_blocks/js/BlockDecisionHandler.js`

**Flow:**
1. **Block Detection**: Scans for `[data-ab-tests-instance-id]` elements
2. **Metadata Enhancement**: `_enhanceBlockMetadata()` enriches block data with decision information
3. **Configuration Merging**: Combines original block settings with variant settings from decider
4. **Decision Validation**: Checks if the merged configuration differs from original

#### 3. Variant Loading and Re-rendering

**Key Differences from View Mode Testing:**

1. **Configuration Encoding**: Block settings are JSON-encoded and base64-encoded for URL transmission
2. **Context Preservation**: Serialized contexts are passed to maintain Layout Builder state
3. **Ajax Endpoint**: Uses `/ab-blocks/ajax-block/{plugin_id}/{placement_id}/{encoded_config}/{encoded_contexts}`

**Server Processing:** `modules/ab_blocks/src/Controller/AjaxBlockRender.php`

**Process:**
1. **Input Decoding**: Base64 decodes configuration and context data
2. **Context Deserialization**: `deserializeContextValues()` rebuilds entity and primitive contexts
3. **Block Instantiation**: Creates block plugin with merged configuration and restored contexts
4. **Block Rendering**: Uses `renderAsBlock()` to maintain HTML parity with non-Ajax rendering
5. **Response**: Returns Ajax response with `InsertCommand` to replace block content

**Key Code Locations:**
- Context deserialization: `AjaxBlockRender.php:199-241`
- Block rendering: `AjaxBlockRender.php:134-162`
- Configuration encoding: `BlockDecisionHandler.js:40-42`

#### 4. Special Block Features

**Block Hiding:**
- If `variantBlockSettings.hide_block` is true, skips Ajax request and removes element from DOM
- Performance optimization: `BlockDecisionHandler.js:31-37`

**Deep Equality Check:**
- `_deepEqual()` method compares original and variant configurations
- Prevents unnecessary Ajax requests when decisions don't change anything

#### 5. Error Handling

**Client-Side Errors:**
- Missing placement ID: Throws descriptive error
- JSON parsing failures: Logs error and continues with empty configuration
- Ajax failures: Logs error and maintains original block

**Server-Side Errors:**
- Invalid JSON: Returns empty response
- Context deserialization failures: Gracefully handles missing contexts
- Block instantiation failures: Caught and handled

### Common Error Handling Patterns

#### JavaScript Error Recovery
- All decision handlers extend `BaseDecisionHandler` which provides consistent error status tracking
- Status progression: `idle` → `in_progress` → `success|error`
- Debug mode provides detailed console logging

#### PHP Error Handling
- Plugin instantiation wrapped in try-catch blocks with fallback to Null plugins
- Ajax responses include proper cache metadata and error states
- Access control respected throughout the pipeline

#### Graceful Degradation
- If JavaScript fails, users see the default variant (server-side rendered)
- If Ajax requests fail, original content remains visible
- Analytics tracking failures don't prevent variant display

### Performance Considerations

#### Client-Side Optimizations
- Decision checks prevent unnecessary Ajax requests when variants don't change
- Block hiding avoids Ajax requests entirely when blocks should be hidden
- Proper cache contexts ensure appropriate caching behavior

#### Server-Side Optimizations
- Context serialization minimizes data transfer while preserving functionality
- Render contexts properly capture and bubble metadata
- Cache dependencies correctly set for all render operations

#### Memory Management
- Plugins instantiated only when needed
- Large configuration objects are base64-encoded for URL transmission
- Proper cleanup of event listeners and temporary DOM modifications
