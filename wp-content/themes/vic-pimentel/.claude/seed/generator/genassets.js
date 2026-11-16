const { chromium } = require('playwright-core');
const fs = require('fs'), path = require('path');
const OUT = path.resolve(__dirname, '../assets');
// Run from a folder with playwright-core installed: NODE_PATH=... node genassets.js (VIDEOS_ONLY=1 to redo just the loops).
const exe = require('os').homedir() + '/.cache/ms-playwright/chromium-1234/chrome-linux64/chrome';

const projects = [
  { slug: 'tianma-america', name: 'Tianma America', a: '#3E7BFA', b: '#0B1E3F', kind: 'web' },
  { slug: 'northwind-bank', name: 'Northwind Bank', a: '#22C49A', b: '#062A23', kind: 'web' },
  { slug: 'atlas-travel', name: 'Atlas Travel', a: '#F2A541', b: '#2B1A06', kind: 'web' },
  { slug: 'lumen-health', name: 'Lumen Health', a: '#9B7BFF', b: '#1A1033', kind: 'web' },
  { slug: 'orbit-analytics', name: 'Orbit Analytics', a: '#FF5D73', b: '#2E0A12', kind: 'web' },
  { slug: 'kinfolk-coffee', name: 'Kinfolk Coffee', a: '#D9A27C', b: '#24160E', kind: 'brand' },
  { slug: 'sora-studio', name: 'Sora Studio', a: '#E9E4D8', b: '#1C1B19', kind: 'brand' },
  { slug: 'verde-market', name: 'Verde Market', a: '#7BC74D', b: '#13240B', kind: 'brand' },
  { slug: 'midnight-tales', name: 'Midnight Tales', a: '#5B8CFF', b: '#070B1C', kind: 'illu' },
  { slug: 'field-notes', name: 'Field Notes', a: '#F06A3C', b: '#1F0C05', kind: 'illu' },
];

const base = `*{margin:0;box-sizing:border-box}html,body{width:100%;height:100%;overflow:hidden;font-family:Inter,Arial,sans-serif}`;

function web(p, t = 0) {
  return `<style>${base}
  body{background:radial-gradient(120% 90% at 85% -10%, ${p.a}55, transparent 60%), ${p.b};color:#fff;padding:4%}
  .nav{display:flex;justify-content:space-between;align-items:center;font-size:2.2vw;opacity:.9}
  .nav i{display:inline-block;width:6vw;height:1.1vw;border-radius:1vw;background:#ffffff40;margin-left:2vw}
  h1{font-family:Georgia,serif;font-weight:400;font-size:7.5vw;line-height:1;margin-top:9%;max-width:70%;letter-spacing:-.03em}
  .sub{margin-top:3%;width:45%;height:1.3vw;border-radius:1vw;background:#ffffff30;box-shadow:0 2.4vw 0 #ffffff20}
  .btn{margin-top:6%;display:inline-block;padding:1.4vw 3vw;border-radius:5vw;background:${p.a};color:#000;font-size:1.8vw;font-weight:600}
  .cards{position:absolute;right:4%;bottom:6%;display:flex;gap:2vw;transform:translateX(${-t * 6}vw)}
  .card{width:17vw;height:21vw;border-radius:1.5vw;background:linear-gradient(160deg,${p.a}aa,${p.a}22);border:1px solid #ffffff22}
  .card:nth-child(2){background:linear-gradient(160deg,#ffffff30,#ffffff08)}
  </style><div class="nav"><b>${p.name}</b><span><i></i><i></i><i></i></span></div>
  <h1>Design that moves people forward.</h1><div class="sub"></div><div class="btn">Get started</div>
  <div class="cards"><div class="card"></div><div class="card"></div><div class="card"></div></div>`;
}

function brand(p, t = 0) {
  return `<style>${base}
  body{background:${p.b};display:grid;place-items:center;color:${p.a}}
  .mark{width:34vw;height:34vw;border-radius:50%;border:2.2vw solid ${p.a};display:grid;place-items:center;transform:rotate(${t * 40}deg)}
  .mark span{font-family:Georgia,serif;font-size:15vw;font-style:italic;transform:rotate(${-t * 40}deg)}
  .name{position:absolute;bottom:9%;font-size:2.4vw;letter-spacing:.4em;text-transform:uppercase}
  .sw{position:absolute;top:8%;left:6%;display:flex;gap:1.4vw}.sw i{width:4vw;height:4vw;border-radius:50%}
  </style><div class="sw"><i style="background:${p.a}"></i><i style="background:#ffffff"></i><i style="background:#ffffff30"></i></div>
  <div class="mark"><span>${p.name[0]}</span></div><div class="name">${p.name}</div>`;
}

function illu(p, t = 0) {
  const stars = Array.from({ length: 40 }, (_, i) => `<i style="left:${(i * 37) % 100}%;top:${(i * 53) % 60}%;opacity:${0.3 + (i % 5) / 8}"></i>`).join('');
  return `<style>${base}
  body{background:linear-gradient(to bottom, ${p.b}, ${p.a}66);}
  .moon{position:absolute;width:16vw;height:16vw;border-radius:50%;background:#fff3d6;left:${62 + t * 4}%;top:${14 - t * 3}%;box-shadow:0 0 8vw #fff3d680}
  .hill{position:absolute;bottom:-30%;border-radius:50%;background:${p.b}}
  .s i{position:absolute;width:.5vw;height:.5vw;border-radius:50%;background:#fff}
  </style><div class="s">${stars}</div><div class="moon"></div>
  <div class="hill" style="left:-20%;width:90%;height:70%;opacity:.9"></div>
  <div class="hill" style="left:35%;width:100%;height:60%;background:${p.a}88"></div>`;
}

const render = (p, t) => (p.kind === 'web' ? web : p.kind === 'brand' ? brand : illu)(p, t);

(async () => {
  const browser = await chromium.launch({ executablePath: exe });
  if (!process.env.VIDEOS_ONLY) {

  // Images (16:10) + favicons + highlight crops
  const page = await browser.newPage({ viewport: { width: 1600, height: 1000 } });
  for (const p of projects) {
    await page.setContent(render(p, 0));
    await page.screenshot({ path: `${OUT}/${p.slug}.jpg`, type: 'jpeg', quality: 86 });
    await page.setContent(render(p, 0.6));
    await page.screenshot({ path: `${OUT}/${p.slug}-alt.jpg`, type: 'jpeg', quality: 86 });
  }
  await page.setViewportSize({ width: 1000, height: 1000 });
  for (const p of projects.slice(0, 6)) {
    await page.setContent(render(p, 0.3));
    await page.screenshot({ path: `${OUT}/${p.slug}-square.jpg`, type: 'jpeg', quality: 86 });
  }
  await page.setViewportSize({ width: 128, height: 128 });
  for (const p of projects) {
    await page.setContent(`<style>${base}body{background:${p.a};display:grid;place-items:center;font:600 64px Georgia,serif;color:${p.b}}</style>${p.name[0]}`);
    await page.screenshot({ path: `${OUT}/favicon-${p.slug}.png` });
  }
  // About portrait + default image
  await page.setViewportSize({ width: 800, height: 800 });
  await page.setContent(`<style>${base}body{background:radial-gradient(60% 60% at 50% 35%, #3a3a3a, #0b0b0b);} .h{position:absolute;left:50%;top:22%;width:34%;height:40%;transform:translateX(-50%);border-radius:50%;background:#1b1b1b} .b{position:absolute;left:50%;bottom:-25%;width:80%;height:55%;transform:translateX(-50%);border-radius:50% 50% 0 0;background:#1b1b1b}</style><div class="h"></div><div class="b"></div>`);
  await page.screenshot({ path: `${OUT}/about-portrait.jpg`, type: 'jpeg', quality: 86 });
  await page.setViewportSize({ width: 1600, height: 1000 });
  await page.setContent(`<style>${base}body{background:radial-gradient(70% 70% at 70% 10%, #2a3b52, transparent 70%), #0b0b0b;display:grid;place-items:center;color:#ffffff30;font:400 120px Georgia,serif;font-style:italic}</style>VP`);
  await page.screenshot({ path: `${OUT}/default-image.jpg`, type: 'jpeg', quality: 86 });

  // PDFs
  await page.setContent(`<style>${base}body{padding:60px;font-family:Georgia,serif}</style><h1>Victor Pimentel — Curriculum</h1><p style="margin-top:20px;font-family:Arial">Placeholder document. Replace it in Site Settings → Footer.</p>`);
  await page.pdf({ path: `${OUT}/victor-pimentel-curriculum.pdf`, format: 'A4' });
  await page.setContent(`<style>${base}body{padding:60px;font-family:Georgia,serif}</style><h1>Victor Pimentel — Digital Card</h1><p style="margin-top:20px;font-family:Arial">Placeholder document.</p>`);
  await page.pdf({ path: `${OUT}/victor-pimentel-digital-card.pdf`, width: '85mm', height: '55mm' });
  await page.close();
  }

  // Looping videos: screenshot each frame, encode an H.264 mp4 with ffmpeg (ffmpeg-static).
  const ffmpeg = require('ffmpeg-static');
  const { execFileSync } = require('child_process');
  for (const p of projects.filter((_, i) => [0, 1, 3, 5, 8].includes(i))) {
    const frameDir = OUT + '/tmp-' + p.slug;
    fs.mkdirSync(frameDir, { recursive: true });
    const vp = await browser.newPage({ viewport: { width: 960, height: 600 } });
    const frames = 120; // 4s @ 30fps, 0 → 1 → 0 so the loop is seamless
    for (let f = 0; f < frames; f++) {
      const t = (1 - Math.cos((f / frames) * Math.PI * 2)) / 2;
      await vp.setContent(render(p, t));
      await vp.screenshot({ path: frameDir + '/' + String(f).padStart(4, '0') + '.png' });
    }
    await vp.close();
    execFileSync(ffmpeg, ['-y', '-loglevel', 'error', '-framerate', '30', '-i', frameDir + '/%04d.png', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '26', '-preset', 'slow', '-movflags', '+faststart', '-an', OUT + '/' + p.slug + '.mp4']);
    fs.rmSync(frameDir, { recursive: true, force: true });
  }
  await browser.close();
  console.log(fs.readdirSync(OUT).join('\n'));
})();
