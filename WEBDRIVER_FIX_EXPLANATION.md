# WebDriver CI Fix: From Complex to Minimal

## Problem Analysis

The current WebDriver setup in `.github/workflows/php.yml` is massively over-engineered with:

- Complex health checks that manually create and delete WebDriver sessions
- Extensive retry logic with custom bash scripts
- Over-specified Chrome options (15+ arguments)
- Manual session cleanup during health checks
- Complex W3C WebDriver testing during startup

**Root Cause**: The complex session creation testing during health checks is likely **interfering with actual test execution**, creating race conditions and resource conflicts.

## Solution: Minimal Standard Configuration

### Key Changes Made:

1. **Simplified Chrome Service**:
   ```yaml
   # BEFORE (complex):
   chrome:
     image: selenium/standalone-chrome:4.15.0
     env:
       SE_NODE_SESSION_TIMEOUT: 300
       SE_NODE_OVERRIDE_MAX_SESSIONS: true
       SE_NODE_MAX_SESSIONS: 3
       SE_START_XVFB: true
     options: --health-cmd="/opt/bin/check-grid.sh" --health-interval=15s --health-timeout=15s --health-retries=15 --shm-size=2g
   
   # AFTER (minimal):
   chrome:
     image: selenium/standalone-chrome:latest
     ports:
       - 4444:4444
     options: --shm-size=2g
   ```

2. **Minimal Chrome Arguments**:
   ```bash
   # BEFORE (15+ arguments):
   MINK_DRIVER_ARGS_WEBDRIVER='["chrome", {"browserName":"chrome","goog:chromeOptions":{"args":["--disable-gpu", "--headless", "--no-sandbox", "--disable-dev-shm-usage", "--disable-extensions", "--disable-background-timer-throttling", "--disable-backgrounding-occluded-windows", "--disable-renderer-backgrounding", "--disable-features=TranslateUI", "--disable-default-apps", "--no-first-run", "--disable-web-security", "--disable-features=VizDisplayCompositor", "--remote-debugging-port=9222", "--disable-ipc-flooding-protection"]},"timeout":60000,"idle_timeout":60000,"connection_timeout":60000},"http://localhost:4444/wd/hub"]'
   
   # AFTER (4 essential arguments):
   MINK_DRIVER_ARGS_WEBDRIVER='["chrome", {"browserName":"chrome","goog:chromeOptions":{"args":["--headless","--no-sandbox","--disable-dev-shm-usage","--disable-gpu"]}}, "http://localhost:4444"]'
   ```

3. **Simple Health Check**:
   ```bash
   # BEFORE (complex session creation testing):
   for i in {1..20}; do
     SESSION_RESPONSE=$(timeout 15 curl -X POST -H "Content-Type: application/json" \
       -d '{"capabilities":{"alwaysMatch":{"browserName":"chrome","goog:chromeOptions":{"args":["--headless","--no-sandbox","--disable-dev-shm-usage","--disable-gpu"]}}}}' \
       http://localhost:4444/wd/hub/session 2>/dev/null || echo "timeout")
     # ... complex session cleanup logic
   done
   
   # AFTER (simple HTTP check):
   for i in {1..30}; do
     if curl -f http://localhost:4444/wd/hub/status >/dev/null 2>&1; then
       echo "Chrome service is ready"
       break
     fi
     sleep 2
   done
   ```

4. **Standard Test Execution**:
   ```bash
   # BEFORE (complex retry script):
   ./webdriver_test_retry.sh
   
   # AFTER (standard phpunit):
   ./vendor/bin/phpunit -c core/phpunit.xml.dist modules/ab_tests/tests/src/FunctionalJavascript
   ```

## Why This Fixes The Problem

1. **Eliminates Race Conditions**: No more manual session testing that interferes with real tests
2. **Reduces Resource Conflicts**: Minimal Chrome options reduce stability issues
3. **Follows Standard Patterns**: Matches how Drupal core and major contrib modules do it
4. **Removes Interference**: Health checks don't create/destroy sessions that could conflict with tests
5. **Simplifies Debugging**: Less moving parts means fewer potential failure points

## Files Changed

- `php.yml.minimal` - New minimal workflow configuration
- `WEBDRIVER_FIX_EXPLANATION.md` - This explanation document

## How To Apply

1. Replace `.github/workflows/php.yml` with the contents of `php.yml.minimal`
2. Remove the `webdriver_test_retry.sh` script creation (no longer needed)
3. Remove all the complex debugging and health check logic
4. Test the simplified configuration

## Expected Result

The FunctionalJavascript tests should now run reliably without session creation timeouts, matching the behavior you see locally with a much simpler and more maintainable configuration.