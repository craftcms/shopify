/* jshint esversion: 9, strict: false */
/* globals module, require */
const {test, expect} = require('../../index');

test.beforeEach(async ({craftDashboard}) => {
  await craftDashboard.goTo();
});

test.describe('Navigation', () => {
  const navItems = [
    ['Shopify', 'Settings'],
  ];

  test('Global navigation has expected links', async ({page}) => {
    await expect(page.locator('#global-sidebar nav ul li a')).toContainText(
      navItems.map((item) => (Array.isArray(item) ? item[0] : item))
    );
  });

  test('Navigation items go to the correct pages', async ({
    craftDashboard,
    page,
  }) => {
    for (let i = 0; i < navItems.length; i++) {
      await craftDashboard.goTo();
      let text = Array.isArray(navItems[i]) ? navItems[i][0] : navItems[i];
      let title = Array.isArray(navItems[i]) ? navItems[i][1] : text;

      await page.click('#global-sidebar nav ul li a:has-text("' + text + '")');
      await expect(page.locator('h1')).toContainText(title);
    }
  });
});
