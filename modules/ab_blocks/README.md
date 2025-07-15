# A/B Blocks

A/B Blocks is a sub-module in the A/B Tests suite that enables A/B testing on
individual blocks. This module allows you to test different block configurations
without requiring blocks to be specifically built for A/B testing.

## Overview

This module works exclusively with Layout Builder and allows you to test
different configurations of block settings. Any block with a configuration form
can be A/B tested by varying the values in that form.

## How It Works

The A/B Blocks module uses a client-side approach to implement block-level
testing:

1. **Initial Placement**: When Layout Builder places a block on the page, the
   module adds special HTML markers to the DOM.
2. **JavaScript Integration**: After page load, JavaScript calls your configured
   decider plugin to determine which variant should be displayed.
3. **Settings Retrieval**: The decider plugin provides a JSON object containing
   the block settings for the selected variant.
4. **Ajax Re-rendering**: The JavaScript makes an Ajax request to re-render the
   block using the settings provided by the decider.
5. **Analytics Tracking**: Once the block is rendered, the analytics tracker
   fires to record the decision and settings for measurement purposes.

## Context Preservation

Blocks often depend on page context (such as the node they're embedded in),
which would normally be lost during Ajax requests. To address this:

- Common contexts, especially entity contexts, are serialized when the original
  block is placed
- These contexts are rehydrated during the Ajax request
- This ensures blocks have access to the same contextual information they would
  have during normal rendering

## Features

### Test Existing Blocks

Any block with a configuration form can be A/B tested without modification. You
simply enable A/B test in Layout Builder, configure your decider and analytics
tracker, and test different values for the block's settings.

### Block Comparison Testing

Using the provided _proxy block_, you can test:

- Block A vs Block B vs Block C ...
- Block vs no block
- Multiple different block types

The proxy block exposes "which block to render" as a configurable setting,
making the block selection itself part of the A/B test.

## Error Handling

The module implements graceful fallback behavior to ensure blocks always render,
even when A/B testing encounters issues:

- **Initial Server Rendering**: All blocks are first rendered server-side using
  their saved Drupal configuration. This block is initially hidden behind a
  loading skeleton.
- **Client-side Enhancement**: The A/B testing functionality then enhances the
  block via JavaScript and Ajax.
- **Fallback Protection**: If client-side rendering fails, the original
  server-rendered version remains visible.

This approach ensures that visitors always see functional content, regardless of
JavaScript errors or network issues that might affect the A/B testing
functionality.

## Current Limitations

- Only works with blocks placed via Layout Builder on nodes
- Other Layout Builder contexts (non-entities) are not yet supported
- Block placement methods other than Layout Builder are not supported

## FAQ

### Can I test pre-existing blocks?

Yes. A/B Blocks allows you to test any block that has a configuration form
without requiring the block to be specifically built for A/B testing. You test
different values for the block's configuration settings.

### Can I test one block against another block (or no block)?

Yes, this is possible using the proxy block provided by this module. The proxy
block allows you to configure which block to render (or render no block at all).
This makes the block selection itself a testable parameter in your A/B test
configuration.
