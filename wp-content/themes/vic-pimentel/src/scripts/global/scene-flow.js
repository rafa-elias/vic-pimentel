/**
 * Built-in animated background — a stand-in for the Unicorn Studio scenes until Victor's
 * exports arrive. Domain-warped noise "flow" in the site palette with a soft light, plus a
 * cursor-following glow/warp on interactive scenes ('hero', 'footer'). 'light' is a slow
 * looping beam for the top of the project/about pages.
 *
 * Cheap on purpose: renders at half resolution, ~30fps, only while on screen and the tab is
 * visible. Never runs for prefers-reduced-motion or without WebGL (CSS fallback stays).
 */
const VERT = `attribute vec2 a;void main(){gl_Position=vec4(a,0.,1.);}`;

const FRAG = `
precision mediump float;
uniform vec2 u_res;
uniform float u_time;
uniform vec2 u_mouse;
uniform float u_hover;
uniform float u_mode;
uniform vec3 u_base;
uniform vec3 u_c1;
uniform vec3 u_c2;
uniform vec3 u_c3;
uniform vec2 u_glow;

float hash(vec2 p){p=fract(p*vec2(123.34,456.21));p+=dot(p,p+45.32);return fract(p.x*p.y);}
float noise(vec2 p){vec2 i=floor(p),f=fract(p);vec2 u=f*f*(3.-2.*f);
  return mix(mix(hash(i),hash(i+vec2(1,0)),u.x),mix(hash(i+vec2(0,1)),hash(i+vec2(1,1)),u.x),u.y);}
float fbm(vec2 p){float v=0.,a=.5;mat2 m=mat2(1.6,1.2,-1.2,1.6);for(int i=0;i<5;i++){v+=a*noise(p);p=m*p;a*=.5;}return v;}

void main(){
  vec2 uv=gl_FragCoord.xy/u_res;
  float aspect=u_res.x/u_res.y;
  float t=u_time;

  if(u_mode>.5){
    // Light beam from the top centre.
    float x=(uv.x-.5)*aspect;
    float y=1.-uv.y;
    float ripple=fbm(vec2(x*2.5,y*1.5-t*.12));
    float spread=.06+.5*y;
    float beam=exp(-(x*x)/(spread*spread))*exp(-y*2.4);
    float halo=exp(-length(vec2(x*.7,y))*2.6);
    vec3 col=u_base+u_c2*beam*(.55+.55*ripple)+u_c3*halo*.12+vec3(1.)*beam*.06*(.6+.8*sin(t*.4+ripple*3.));
    gl_FragColor=vec4(col,1.);
    return;
  }

  vec2 p=uv*vec2(aspect,1.)*1.4;
  vec2 m=u_mouse*vec2(aspect,1.)*1.4;
  float d=distance(p,m);
  float pull=exp(-d*d*2.5)*u_hover;
  p+=(p-m)*.25*pull;

  vec2 q=vec2(fbm(p+t*.035),fbm(p+vec2(5.2,1.3)-t*.03));
  vec2 r=vec2(fbm(p+3.*q+vec2(1.7,9.2)+t*.05),fbm(p+3.*q+vec2(8.3,2.8)-t*.04));
  float f=fbm(p+3.*r);

  vec3 col=mix(u_base,u_c1,smoothstep(.25,.95,f));
  col+=u_c2*pow(clamp(r.x,0.,1.),3.)*.55;

  vec2 g=(uv-u_glow)*vec2(aspect*.8,1.5);
  float glow=exp(-length(g)*2.6)*(.55+.45*f);
  col+=u_c2*glow*.45+u_c3*glow*glow*.55;

  col+=u_c2*pull*.22*(.6+.4*f);

  float vig=smoothstep(1.25,.25,length((uv-.5)*vec2(aspect*.7,1.)));
  col=mix(u_base,col,.35+.65*vig)*.85;
  gl_FragColor=vec4(col,1.);
}`;

const hex = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16) / 255);

const PRESETS = {
    hero: {mode: 0, base: '#030303', c1: '#121a26', c2: '#3e5c86', c3: '#c9874b', glow: [0.72, 1.02]},
    login: {mode: 0, base: '#030303', c1: '#111824', c2: '#3a5680', c3: '#b98049', glow: [0.55, 1.08]},
    footer: {mode: 0, base: '#1b1b1b', c1: '#0b0b0b', c2: '#2d3442', c3: '#4a3a2a', glow: [0.3, -0.1]},
    light: {mode: 1, base: '#030303', c1: '#030303', c2: '#4a6a99', c3: '#c9874b', glow: [0.5, 1]},
};

export function startFlow(el, key, interactive) {
    const preset = PRESETS[key] || PRESETS.hero;
    const canvas = document.createElement('canvas');
    canvas.className = 'scene-flow';
    const gl = canvas.getContext('webgl', {antialias: false, alpha: false, powerPreference: 'low-power'});
    if (!gl) return;

    const shader = (type, src) => {
        const s = gl.createShader(type);
        gl.shaderSource(s, src);
        gl.compileShader(s);
        return gl.getShaderParameter(s, gl.COMPILE_STATUS) ? s : null;
    };

    const vs = shader(gl.VERTEX_SHADER, VERT);
    const fs = shader(gl.FRAGMENT_SHADER, FRAG);
    if (!vs || !fs) return;

    const program = gl.createProgram();
    gl.attachShader(program, vs);
    gl.attachShader(program, fs);
    gl.linkProgram(program);
    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) return;
    gl.useProgram(program);

    gl.bindBuffer(gl.ARRAY_BUFFER, gl.createBuffer());
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([-1, -1, 3, -1, -1, 3]), gl.STATIC_DRAW);
    const loc = gl.getAttribLocation(program, 'a');
    gl.enableVertexAttribArray(loc);
    gl.vertexAttribPointer(loc, 2, gl.FLOAT, false, 0, 0);

    const u = name => gl.getUniformLocation(program, name);
    const uni = {res: u('u_res'), time: u('u_time'), mouse: u('u_mouse'), hover: u('u_hover')};
    gl.uniform1f(u('u_mode'), preset.mode);
    gl.uniform3fv(u('u_base'), hex(preset.base));
    gl.uniform3fv(u('u_c1'), hex(preset.c1));
    gl.uniform3fv(u('u_c2'), hex(preset.c2));
    gl.uniform3fv(u('u_c3'), hex(preset.c3));
    gl.uniform2fv(u('u_glow'), preset.glow);

    el.appendChild(canvas);

    const SCALE = 0.5;
    const resize = () => {
        canvas.width = Math.max(1, Math.round(el.clientWidth * SCALE));
        canvas.height = Math.max(1, Math.round(el.clientHeight * SCALE));
        gl.viewport(0, 0, canvas.width, canvas.height);
        gl.uniform2f(uni.res, canvas.width, canvas.height);
    };
    resize();
    new ResizeObserver(resize).observe(el);

    // Cursor, smoothed. Listens on window so it keeps tracking over the content on top.
    const target = {x: 0.5, y: 0.5, hover: 0};
    const pos = {x: 0.5, y: 0.5, hover: 0};

    if (interactive && window.matchMedia('(hover: hover)').matches) {
        window.addEventListener('pointermove', event => {
            const rect = el.getBoundingClientRect();
            const inside = event.clientY >= rect.top && event.clientY <= rect.bottom;
            target.x = (event.clientX - rect.left) / rect.width;
            target.y = 1 - (event.clientY - rect.top) / rect.height;
            target.hover = inside ? 1 : 0;
        }, {passive: true});
    }

    let visible = false;
    let raf = 0;
    let last = 0;
    const start = performance.now() - Math.random() * 20000;

    const frame = now => {
        raf = 0;
        if (!visible || document.hidden) return;
        raf = requestAnimationFrame(frame);
        if (now - last < 33) return; // ~30fps
        last = now;

        pos.x += (target.x - pos.x) * 0.08;
        pos.y += (target.y - pos.y) * 0.08;
        pos.hover += (target.hover - pos.hover) * 0.05;

        gl.uniform1f(uni.time, (now - start) / 1000);
        gl.uniform2f(uni.mouse, pos.x, pos.y);
        gl.uniform1f(uni.hover, pos.hover);
        gl.drawArrays(gl.TRIANGLES, 0, 3);

        if (!el.classList.contains('is-ready')) el.classList.add('is-ready');
    };

    const play = () => { if (!raf && visible && !document.hidden) raf = requestAnimationFrame(frame); };

    new IntersectionObserver(([entry]) => {
        visible = entry.isIntersecting;
        play();
    }).observe(el);

    document.addEventListener('visibilitychange', play);
}
