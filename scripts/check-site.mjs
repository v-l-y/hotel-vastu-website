import fs from "node:fs";
import path from "node:path";

const root=process.cwd();
const errors=[];
const warnings=[];
const ok=(label)=>console.log("✓",label);
const fail=(label,detail="")=>errors.push(detail?label+": "+detail:label);
const warn=(label,detail="")=>warnings.push(detail?label+": "+detail:label);
const read=(file)=>fs.readFileSync(path.join(root,file),"utf8");
const exists=(file)=>fs.existsSync(path.join(root,file));
const htmlFiles=fs.readdirSync(root).filter(f=>f.endsWith(".html")).sort();
const indexable=new Set();
const noindex=new Set();
const seenTitles=new Map();
const seenCanonicals=new Map();

function attr(html,tag,name){
  const re=new RegExp("<"+tag+"[^>]*\\b"+name+"=[\\\"']([^\\\"']+)[\\\"'][^>]*>","i");
  return html.match(re)?.[1]??null;
}
function meta(html,name){
  const re=new RegExp("<meta[^>]*\\bname=[\\\"']"+name+"[\\\"'][^>]*\\bcontent=[\\\"']([^\\\"']*)[\\\"'][^>]*>","i");
  const rev=new RegExp("<meta[^>]*\\bcontent=[\\\"']([^\\\"']*)[\\\"'][^>]*\\bname=[\\\"']"+name+"[\\\"'][^>]*>","i");
  return html.match(re)?.[1]??html.match(rev)?.[1]??null;
}
function propertyMeta(html,name){
  const re=new RegExp("<meta[^>]*\\bproperty=[\\\"']"+name+"[\\\"'][^>]*\\bcontent=[\\\"']([^\\\"']*)[\\\"'][^>]*>","i");
  const rev=new RegExp("<meta[^>]*\\bcontent=[\\\"']([^\\\"']*)[\\\"'][^>]*\\bproperty=[\\\"']"+name+"[\\\"'][^>]*>","i");
  return html.match(re)?.[1]??html.match(rev)?.[1]??null;
}
function localTarget(from,raw){
  if(!raw||raw.startsWith("#")||/^(https?:|tel:|mailto:|data:|javascript:)/i.test(raw)) return null;
  const clean=raw.split("#")[0].split("?")[0];
  if(!clean) return null;
  const rel=clean.startsWith("/")?clean.slice(1):path.normalize(path.join(path.dirname(from),clean));
  return rel.endsWith("/")?path.join(rel,"index.html"):rel;
}

for(const file of htmlFiles){
  const html=read(file);
  const robots=(meta(html,"robots")||"").toLowerCase();
  const isNoindex=robots.includes("noindex")||file==="404.html";
  (isNoindex?noindex:indexable).add(file);

  const title=html.match(/<title>([^<]+)<\/title>/i)?.[1]?.trim();
  if(!title) fail(file,"missing <title>");
  else { if(title.length>65) warn(file,"title is "+title.length+" characters"); const prior=seenTitles.get(title); if(prior) fail(file,"duplicate title also used by "+prior); else seenTitles.set(title,file); }

  const description=meta(html,"description");
  if(!isNoindex&&!description) fail(file,"missing meta description");
  if(description&&description.length>170) warn(file,"meta description is "+description.length+" characters");

  const h1Count=(html.match(/<h1\b/gi)||[]).length;
  if(h1Count!==1) fail(file,"expected exactly one H1, found "+h1Count);

  const canonical=html.match(/<link[^>]*rel=[\"']canonical[\"'][^>]*href=[\"']([^\"']+)[\"']/i)?.[1]
    ??html.match(/<link[^>]*href=[\"']([^\"']+)[\"'][^>]*rel=[\"']canonical[\"']/i)?.[1];
  if(!isNoindex&&!canonical) fail(file,"missing canonical URL");
  if(canonical&&!canonical.startsWith("https://hotelvastu.com/")) fail(file,"canonical uses unexpected host");
  if(canonical){const prior=seenCanonicals.get(canonical);if(prior)fail(file,"duplicate canonical also used by "+prior);else seenCanonicals.set(canonical,file);}
  if(!isNoindex&&canonical){const expected=file==="index.html"?"https://hotelvastu.com/":"https://hotelvastu.com/"+file;if(canonical!==expected)fail(file,"canonical mismatch; expected "+expected);}

  if(!isNoindex){
    const ogTitle=propertyMeta(html,"og:title");
    const ogDescription=propertyMeta(html,"og:description");
    const ogUrl=propertyMeta(html,"og:url");
    const ogSite=propertyMeta(html,"og:site_name");
    if(!ogTitle) fail(file,"missing og:title");
    if(!ogDescription) fail(file,"missing og:description");
    if(!ogUrl) fail(file,"missing og:url");
    if(!ogSite) fail(file,"missing og:site_name");
    if(title&&ogTitle&&title!==ogTitle) fail(file,"og:title does not match <title>");
    if(description&&ogDescription&&description!==ogDescription) fail(file,"og:description does not match meta description");
    if(canonical&&ogUrl&&canonical!==ogUrl) fail(file,"og:url does not match canonical");
  }

  for(const img of html.matchAll(/<img\\b[^>]*>/gi)){
    const tag=img[0];
    if(!/\\balt=[\\\"'][^\\\"']*[\\\"']/i.test(tag)) fail(file,"img missing alt attribute");
    if(!/\\bwidth=[\\\"']?\\d+/i.test(tag)) fail(file,"img missing intrinsic width");
    if(!/\\bheight=[\\\"']?\\d+/i.test(tag)) fail(file,"img missing intrinsic height");
  }

  for(const m of html.matchAll(/<(?:a|link|script|img)[^>]*\b(?:href|src)=[\"']([^\"']+)[\"']/gi)){
    const target=localTarget(file,m[1]);
    if(target&&!exists(target)) fail(file,"missing local target "+m[1]);
  }

  for(const m of html.matchAll(/<script[^>]*type=[\"']application\/ld\+json[\"'][^>]*>([\s\S]*?)<\/script>/gi)){
    try{JSON.parse(m[1]);}catch(e){fail(file,"invalid JSON-LD: "+e.message);}
  }
}

for(const cssDir of ["css"]){
  if(!exists(cssDir)) continue;
  for(const file of fs.readdirSync(path.join(root,cssDir)).filter(f=>f.endsWith(".css"))){
    const rel=path.join(cssDir,file);
    const css=read(rel);
    for(const m of css.matchAll(/url\((?:[\"']?)([^)\"']+)(?:[\"']?)\)/gi)){
      const raw=m[1].trim();
      if(/^(data:|https?:)/i.test(raw)) continue;
      const target=path.normalize(path.join(path.dirname(rel),raw));
      if(!exists(target)) fail(rel,"missing asset "+raw);
    }
  }
}

try{JSON.parse(read("site.webmanifest"));}catch(e){fail("site.webmanifest","invalid JSON: "+e.message);}

const sitemap=read("sitemap.xml");
const locs=[...sitemap.matchAll(/<loc>([^<]+)<\/loc>/g)].map(m=>m[1]);
const sitemapFiles=new Set(locs.map(url=>{
  const u=new URL(url);
  if(u.origin!=="https://hotelvastu.com") fail("sitemap.xml","unexpected host "+u.origin);
  return u.pathname==="/"? "index.html":u.pathname.replace(/^\//,"");
}));

for(const file of sitemapFiles){
  if(!exists(file)) fail("sitemap.xml","URL points to missing file "+file);
  if(noindex.has(file)) fail("sitemap.xml","contains noindex page "+file);
}
for(const file of indexable){
  if(file==="404.html") continue;
  if(!sitemapFiles.has(file)) fail("sitemap.xml","missing indexable page "+file);
}
for(const file of noindex){
  if(sitemapFiles.has(file)) fail("sitemap.xml","noindex page is listed "+file);
}

const robots=read("robots.txt");
if(!robots.includes("Sitemap: https://hotelvastu.com/sitemap.xml")) fail("robots.txt","canonical sitemap declaration missing");

const htaccess=read(".htaccess");
if(!htaccess.includes("RewriteRule ^room/classic-room/?$ /classic-room.html [R=301,L]")) fail(".htaccess","legacy Classic Room 301 redirect missing");

const home=read("index.html");
for(const expected of ["+918002007466","RPS Law College","ChIJDxeajLFX7TkRZBzzwB6YHzg"]){
  if(!home.includes(expected)) fail("index.html","business source-of-truth token missing: "+expected);
}

if(warnings.length){
  console.log("\nWarnings:");
  for(const item of warnings) console.log("!",item);
}
if(errors.length){
  console.error("\nStatic site QA failed:");
  for(const item of errors) console.error("✗",item);
  process.exit(1);
}
console.log("\nStatic site QA passed.");
console.log("Checked",htmlFiles.length,"HTML files,",sitemapFiles.size,"sitemap URLs, local links/assets, JSON-LD, manifest, robots and redirects.");
