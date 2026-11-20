/** Jalankan dengan Node 22+, PHP CLI/PDO MySQL dan Chrome. */
import assert from 'node:assert/strict';
import { randomBytes } from 'node:crypto';
import { cpSync, existsSync, mkdtempSync, readFileSync, rmSync } from 'node:fs';
import { spawn, spawnSync } from 'node:child_process';
import { once } from 'node:events';
import { createServer } from 'node:net';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { setTimeout as delay } from 'node:timers/promises';

const root = fileURLToPath(new URL('../kos-aron/', import.meta.url));
const temp = mkdtempSync(join(tmpdir(), 'kos-aron-responsive-'));
const app = join(temp, 'app');
const db = 'uji_aron_10_' + randomBytes(8).toString('hex');
const password = randomBytes(24).toString('hex');
const env = { ...process.env, ARON_RESP_DB: db, ARON_RESP_PASS: password };
const setup = "require 'includes/database.local.php'; $name=getenv('ARON_RESP_DB'); if(!preg_match('/^uji_aron_10_[0-9a-f]{16}$/D',$name)) exit(2); $s=new PDO('mysql:host='.DB_HOST.';port='.DB_PORT, DB_USER,DB_PASS); ";
const sizes = [[1440,900],[1024,768],[768,1024],[390,844],[360,800]];
const groups = [
    ['', '', ['index.php','kamar.php','detail-kamar.php?id=1','detail-kamar.php?id=2','detail-kamar.php?id=4','pesan-kamar.php?id=1','tentang.php','kontak.php','login.php']],
    ['admin/', 'admin', ['index.php','kamar.php','kamar-form.php','kamar-form.php?id=1','penghuni.php','penghuni-form.php','penghuni-form.php?id=1','penghuni-detail.php?id=1','pemesanan.php','pemesanan-detail.php?id=1','tagihan.php','tagihan.php?form=baru','tagihan.php?form=massal','pembayaran.php','pembayaran.php?form=baru','pembayaran.php?lihat=2','laporan.php?bulan=&tahun=','pengaturan.php']],
    ['penghuni/', 'ahmad.fauzi', ['index.php','kamar.php','tagihan.php','riwayat.php','riwayat.php?lihat=2','notifikasi.php','profil.php']],
];
function php(cwd, code, ...args) {
    const result = spawnSync('php', ['-r', code, ...args], { cwd, env, encoding:'utf8', windowsHide:true });
    if (result.status !== 0) throw Error(result.stderr || result.error || 'PHP gagal');
}
async function waitUntil(check, label) {
    for (let n=0; n<160; n++) {
        if (await check()) return;
        await delay(80);
    }
    throw Error('Timeout ' + label);
}
async function freePort() {
    const server = createServer();
    server.listen(0, '127.0.0.1');
    await once(server, 'listening');
    const port = server.address().port;
    await new Promise(resolve => server.close(resolve));
    return port;
}
async function stop(child) {
    if (!child || child.exitCode !== null || child.signalCode) return;
    const done = once(child, 'exit');
    child.kill();
    await done;
}
async function cdp(url) {
    const ws = new WebSocket(url);
    await once(ws, 'open');
    let id = 0;
    const pending = new Map();
    ws.onmessage = event => {
        const msg = JSON.parse(event.data);
        if (!pending.has(msg.id)) return;
        const { resolve, reject, timeout } = pending.get(msg.id);
        clearTimeout(timeout);
        pending.delete(msg.id);
        if (msg.error) reject(Error(msg.error.message));
        else resolve(msg.result);
    };
    return { close: () => ws.close(), call(method, params={}) {
        return new Promise((resolve, reject) => {
            const key = ++id;
            const timeout = setTimeout(() => { pending.delete(key); reject(Error('CDP timeout ' + method)); }, 12000);
            pending.set(key, { resolve, reject, timeout });
            ws.send(JSON.stringify({ id:key, method, params }));
        });
    }};
}

// Fungsi ini dijalankan pada DOM yang dirender Chrome.
function measure() {
    const viewport = document.documentElement.clientWidth;
    const visible = el => el.getClientRects().length && getComputedStyle(el).visibility !== 'hidden';
    const label = el => el.id || el.name || el.className || el.tagName;
    const issues = [];
    const page = document.scrollingElement.scrollWidth;
    if (page > viewport + 1) issues.push('dokumen ' + page + '/' + viewport);
    const modal = document.querySelector('dialog[open]');
    if (modal) modal.querySelectorAll('details').forEach(el => { el.open = true; });
    const scope = modal || document.querySelector('main');
    if (!scope || !document.querySelector('h1')) throw Error('Konten halaman hilang');
    const fields = [...scope.querySelectorAll('input:not([type=hidden]),select,textarea,button,a.btn')].filter(visible);
    for (const el of fields) {
        if (el.closest('.tabel-wrap')) continue;
        const r = el.getBoundingClientRect();
        if (r.left < -1 || r.right > viewport+1) issues.push(label(el)+' x='+Math.round(r.left)+'..'+Math.round(r.right));
        for (let parent=el.parentElement; parent && parent!==document.body; parent=parent.parentElement) {
            if (!['hidden','clip','auto','scroll'].includes(getComputedStyle(parent).overflowX)) continue;
            const box=parent.getBoundingClientRect();
            if (r.left < box.left-1 || r.right > box.right+1) {
                issues.push(label(el)+' ujung '+Math.round(r.right)+' melampaui '+label(parent)+' ('+Math.round(box.right)+')');
                break;
            }
        }
    }
    for (const el of scope.querySelectorAll('h1,h2,h3,p,dt,dd,.badge,.form-label')) {
        if (!visible(el) || !el.textContent.trim() || el.closest('.tabel-wrap,[hidden],details:not([open])')) continue;
        const range = document.createRange();
        range.selectNodeContents(el);
        for (const rect of range.getClientRects()) {
            if (rect.width && (rect.left < -1 || rect.right > viewport+1)) {
                issues.push('teks keluar viewport '+label(el));
                break;
            }
        }
    }
    const wrappers = [...scope.querySelectorAll('.tabel-wrap')].filter(visible);
    let scrollable = 0;
    for (const box of wrappers) {
        if (box.scrollWidth <= box.clientWidth+1) continue;
        box.scrollLeft = box.scrollWidth;
        if (box.scrollLeft < box.scrollWidth-box.clientWidth-1) issues.push('tabel tidak dapat digeser');
        box.scrollLeft = 0;
        scrollable++;
    }
    if (modal) {
        const r = modal.getBoundingClientRect();
        if (r.left < -1 || r.right > viewport+1 || r.top < -1 || r.bottom>innerHeight+1) issues.push('dialog di luar viewport');
    }
    return { windowWidth:innerWidth, viewport, page, fields:fields.length, scrollable, modal:!!modal, issues:[...new Set(issues)] };
}

function checkMenu() {
    const toggle=document.querySelector('.nav-toggle,[data-sidebar-toggle]');
    const menu=document.getElementById(toggle.getAttribute('aria-controls'));
    const mobile=innerWidth<(menu.id==='sidebar'?1024:768);
    if (!!toggle.offsetWidth!==mobile) return false;
    if (!mobile) return !!menu.offsetWidth;
    toggle.click();
    const open=toggle.getAttribute('aria-expanded')==='true' && !!menu.offsetWidth;
    document.dispatchEvent(new KeyboardEvent('keydown',{key:'Escape'}));
    return open && toggle.getAttribute('aria-expanded')==='false' && !menu.offsetWidth && document.activeElement===toggle;
}

let server, chrome, socket, created = false;
try {
    cpSync(root, app, { recursive:true, filter:path=>!/(?:^|[\\/])(?:database\.local\.php|docs)(?:[\\/]|$)/.test(path) });
    php(root, setup+'$s->exec("CREATE DATABASE $name CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");');
    created = true;
    php(root, setup+String.raw`$conf="<?php\n"; foreach (['DB_HOST'=>DB_HOST,'DB_PORT'=>DB_PORT,'DB_USER'=>DB_USER,'DB_PASS'=>DB_PASS,'DB_NAME'=>$name] as $k=>$v) $conf.='define('.var_export($k,true).','.var_export($v,true).");\n"; file_put_contents($argv[1],$conf);`, join(app,'includes','database.local.php'));
    php(app, `require 'includes/koneksi.php'; $db=koneksi_database(); if ($db->query('SELECT DATABASE()')->fetchColumn()!==getenv('ARON_RESP_DB')) exit(2);
        foreach (['schema.sql'=>'CREATE TABLE users (','migrasi-registrasi-pemesanan.sql'=>'CREATE TABLE IF NOT EXISTS calon_penghuni','seed.sql'=>'SET FOREIGN_KEY_CHECKS'] as $file=>$start) {
            $sql=file_get_contents('database/'.$file); $index=strpos($sql,$start); if ($index===false) exit(2);
            $db->exec(substr($sql,$index));
        }
        $db->exec("INSERT INTO calon_penghuni (nama_lengkap,nomor_telepon,tanggal_daftar) VALUES ('Calon Uji Tampilan','080000000123',CURRENT_DATE)");
        $db->exec("INSERT INTO pemesanan (kode_pemesanan,calon_penghuni_id,kamar_id,tanggal_pengajuan,rencana_tanggal_masuk) VALUES ('KA-0000000000000001',1,1,NOW(),CURRENT_DATE)");
        $db->prepare("UPDATE users SET status='aktif', password_hash=? WHERE username IN ('admin','ahmad.fauzi')")->execute([password_hash(getenv('ARON_RESP_PASS'),PASSWORD_DEFAULT)]);`);
    const port = await freePort();
    const base = 'http://127.0.0.1:'+port+'/';
    server = spawn('php',['-d','session.save_path="'+temp.replaceAll('\\','/')+'"','-S','127.0.0.1:'+port,'-t',app],{stdio:'ignore',windowsHide:true});
    await waitUntil(async () => { try { return (await fetch(base)).ok; } catch { return false; } }, 'PHP');
    const profile = join(temp,'profile');
    const chromeBin = process.env.CHROME_BIN || (process.platform==='win32' ? join(process.env.ProgramFiles,'Google/Chrome/Application/chrome.exe') : 'google-chrome');
    chrome = spawn(chromeBin,['--headless','--disable-gpu','--no-first-run','--disable-extensions','--disable-background-networking','--remote-debugging-address=127.0.0.1','--remote-debugging-port=0','--user-data-dir='+profile,'about:blank'],{stdio:'ignore',windowsHide:true});
    const activePort = join(profile,'DevToolsActivePort');
    await waitUntil(() => existsSync(activePort) && readFileSync(activePort,'utf8').includes('\n'), 'Chrome');
    const endpoint = 'http://127.0.0.1:'+readFileSync(activePort,'utf8').split('\n')[0].trim();
    const tab = (await (await fetch(endpoint+'/json/list')).json()).find(item=>item.type==='page');
    socket = await cdp(tab.webSocketDebuggerUrl);
    const {call} = socket;
    const version = await call('Browser.getVersion');
    console.log(version.product);
    await call('Page.enable');
    await call('Network.enable');
    await call('Network.setBlockedURLs',{urls:['*://fonts.googleapis.com/*','*://fonts.gstatic.com/*','*://*.google.com/*']});
    async function evaluate(expression) {
        const result = await call('Runtime.evaluate', {expression, awaitPromise:true, returnByValue:true});
        if (result.exceptionDetails) throw Error(result.exceptionDetails.exception?.description || result.exceptionDetails.text);
        return result.result.value;
    }
    async function navigate(path) {
        await call('Page.navigate', {url:base+path});
        await waitUntil(()=>evaluate("location.href==="+JSON.stringify(base+path)+" && document.readyState==='complete'"), path);
    }
    const totals = sizes.map(([width])=>({width,pages:0,fields:0,scrollable:0,dialogs:0,menus:0}));
    let errors=0;
    for (const [prefix,user,paths] of groups) {
        await call('Network.clearBrowserCookies');
        if (user) {
            await navigate('login.php');
            await evaluate("document.querySelector('[name=identitas]').value="+JSON.stringify(user)+";document.querySelector('[name=password]').value="+JSON.stringify(password)+";document.querySelector('[name=identitas]').form.requestSubmit(); true");
            await waitUntil(()=>evaluate("location.pathname==="+JSON.stringify('/'+prefix+'index.php')+" && document.readyState==='complete'"), 'login '+prefix);
        }
        for (const path of paths) {
            await navigate(prefix+path);
            for (const [i,[width,height]] of sizes.entries()) {
                await call('Emulation.setDeviceMetricsOverride',{width,height,deviceScaleFactor:1,mobile:false});
                await evaluate("new Promise(resolve=>requestAnimationFrame(()=>requestAnimationFrame(resolve)))");
                const data = await evaluate('('+measure.toString()+')()');
                assert.equal(data.windowWidth, width);
                if ((!prefix && ['index.php','kamar.php','detail-kamar.php?id=1','detail-kamar.php?id=2','detail-kamar.php?id=4','tentang.php'].includes(path)) ||
                    (prefix === 'admin/' && path === 'kamar.php')) {
                    const images = await evaluate(`(async () => {
                        const info = [];
                        for (const img of document.querySelectorAll('main img')) {
                            img.scrollIntoView({block:'center'});
                            await img.decode();
                            const source = img.currentSrc;
                            const url = new URL(source);
                            const response = await fetch(source);
                            if (!response.ok || url.origin !== location.origin || !img.naturalWidth)
                                throw Error('Aset lokal gagal ' + url.pathname);
                            if (url.pathname.includes('-ilustrasi')) {
                                const label = img.parentElement.querySelector('small');
                                if (!label || label.textContent.trim() !== 'Foto ilustrasi' || !img.alt ||
                                    img.naturalWidth !== 1280 || img.naturalHeight !== 960 ||
                                    getComputedStyle(img).objectFit !== 'cover')
                                    throw Error('Label, ukuran atau object-fit ' + url.pathname);
                                info.push(url.pathname.split('/').pop());
                            }
                        }
                        window.scrollTo(0,0);
                        return info.sort();
                    })()`);
                    const rooms = ['kamar-ilustrasi-01.webp','kamar-ilustrasi-02.webp','kamar-ilustrasi-03.webp'];
                    const expected = path === 'index.php' ? [...rooms.slice(0,2),'kos-aron-hero-ilustrasi.webp'] :
                        path === 'tentang.php' ? ['kos-aron-tentang-ilustrasi.webp'] :
                        path.startsWith('detail') ? [rooms[{'1':0,'2':1,'4':2}[path.split('=').pop()]]] : rooms;
                    assert.deepEqual(images, expected.sort(), prefix + path + ' ' + width);
                }
                if (path.includes('form=') || path.includes('lihat=')) assert.ok(data.modal, path);
                const row=totals[i];
                row.pages++; row.fields+=data.fields; row.scrollable+=data.scrollable; row.dialogs+=Number(data.modal);
                if (path==='index.php') {
                    if (!await evaluate('('+checkMenu.toString()+')()')) data.issues.push('navigasi');
                    else row.menus++;
                }
                if (data.issues.length) { errors++; console.log('GAGAL',width,prefix+path,JSON.stringify(data.issues)); }
            }
        }
    }
    console.table(totals);
    if (errors) throw Error(errors+' kombinasi gagal');
} finally {
    if (socket) {
        try { await socket.call('Browser.close'); } catch {}
        socket.close();
    }
    await stop(chrome);
    await stop(server);
    try {
        if (created) php(root,setup+'$s->exec("DROP DATABASE $name");');
    } finally {
        rmSync(temp,{recursive:true,force:true,maxRetries:12,retryDelay:150});
    }
}
