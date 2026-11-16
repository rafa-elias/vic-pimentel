// Site icon: "Vp" monogram — italic serif V + roman P, like the VicPimentel logotype —
// on the site's near-black with a soft blue light. Output: .claude/seed/assets/site-icon.png
const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const theme = path.resolve(__dirname, '../../..');
const font = f => 'data:font/woff2;base64,' + fs.readFileSync(path.join(theme, 'src/assets/fonts', f)).toString('base64');
const exe = process.env.CHROME || require('os').homedir() + '/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome';
(async () => {
  const browser = await chromium.launch({ executablePath: exe });
  const page = await browser.newPage({ viewport: { width: 512, height: 512 } });
  await page.setContent(`<style>
    @font-face{font-family:IS;src:url(${font('instrument-serif-latin.woff2')})}
    @font-face{font-family:IS;font-style:italic;src:url(${font('instrument-serif-italic-latin.woff2')})}
    *{margin:0}html,body{width:512px;height:512px;background:transparent}
    .icon{width:512px;height:512px;border-radius:112px;overflow:hidden;position:relative;display:grid;place-items:center;
      background:radial-gradient(60% 55% at 72% 8%, rgba(62,92,134,.55), transparent 70%),radial-gradient(45% 35% at 80% 0%, rgba(201,135,75,.28), transparent 70%),#0b0b0b}
    .icon::after{content:'';position:absolute;inset:0;border-radius:inherit;box-shadow:inset 0 0 0 4px rgba(255,255,255,.06)}
    .mono{font-family:IS;color:#fff;font-size:330px;line-height:1;letter-spacing:0;transform:translate(-4px,8px)}
    .mono i{font-style:italic;margin-right:0.06em}
  </style><div class="icon"><span class="mono"><i>V</i>P</span></div>`);
  await page.evaluate(() => document.fonts.ready);
  await page.waitForTimeout(300);
  await page.screenshot({ path: path.join(theme, '.claude/seed/assets/site-icon.png'), omitBackground: true });
  await browser.close();
})();
