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

  const themeColor=meta(html,"theme-color");
  if(themeColor!=="#211a16") fail(file,"theme-color must match luxury palette #211a16");
  const logoRefs=(html.match(/src=["']images\/branding\/hotel-vastu-logo\.png["']/gi)||[]).length;
  if(logoRefs<2) fail(file,"header and footer must both use semantic original logo images");
  if(!html.includes('class="brand brand-logo"')) fail(file,"header semantic logo wrapper missing");
  if(!html.includes('class="footer-brand-logo"')) fail(file,"footer semantic logo wrapper missing");
  if(!html.includes("Comfortable stays near RPS More, Patna.")) fail(file,"canonical footer tagline missing");
  if(html.includes("Comfortable stays at RPS More")) fail(file,"stale footer tagline remains");
  const footer=html.match(/<footer class="footer">[\s\S]*?<\/footer>/i)?.[0]||"";
  for(const href of ["rooms.html","facilities.html","restaurant.html","gallery.html","contact.html","hotel-near-rps-more.html","hotel-near-danapur-railway-station.html","privacy.html","booking-information.html"]){
    if(!footer.includes(`href="${href}"`)) fail(file,"canonical footer link missing: "+href);
  }

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
    const ogImage=propertyMeta(html,"og:image");
    if(!ogTitle) fail(file,"missing og:title");
    if(!ogDescription) fail(file,"missing og:description");
    if(!ogUrl) fail(file,"missing og:url");
    if(!ogSite) fail(file,"missing og:site_name");
    if(!ogImage) fail(file,"missing og:image");
    if(ogImage&&!ogImage.startsWith("https://hotelvastu.com/")) fail(file,"og:image must use canonical hotelvastu.com host");
    if(ogImage&&/\.svg(?:\?|$)/i.test(ogImage)) fail(file,"og:image must be a raster social preview image");
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

let manifest=null;
try{manifest=JSON.parse(read("site.webmanifest"));}catch(e){fail("site.webmanifest","invalid JSON: "+e.message);}

// Manifest/branding guard: browser/PWA branding must use the original Hotel Vastu logo artwork.
if(manifest){
  if(manifest.theme_color!=="#211a16") fail("site.webmanifest","theme_color must be #211a16");
  if(manifest.background_color!=="#fffdfa") fail("site.webmanifest","background_color must be #fffdfa");
  const iconMap=new Map((manifest.icons||[]).map(icon=>[icon.sizes,icon]));
  for(const [sizes,src] of [["192x192","/images/branding/hotel-vastu-icon-192.png"],["512x512","/images/branding/hotel-vastu-icon-512.png"]]){
    const icon=iconMap.get(sizes);
    if(!icon||icon.src!==src||icon.type!=="image/png") fail("site.webmanifest","missing branded "+sizes+" PNG icon");
    const rel=src.replace(/^\//,"");
    if(!exists(rel)) fail("site.webmanifest","missing manifest icon file "+rel);
  }
}
if(exists("favicon.svg")) fail("branding","obsolete custom V favicon.svg must not return");
for(const file of htmlFiles){
  const html=read(file);
  if(!html.includes('href="images/branding/hotel-vastu-icon-192.png" type="image/png" sizes="192x192"')) fail(file,"branded PNG favicon link missing");
}

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

// Security header guard.
for(const token of [
  'Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"',
  'Header always set Content-Security-Policy "default-src \'self\';',
  'Header always set Permissions-Policy "camera=(), microphone=(), geolocation=()"'
]){
  if(!htaccess.includes(token)) fail(".htaccess","missing security policy token: "+token);
}

// Production cache guard: fixed filenames need short cache windows to avoid stale redesign assets.
for(const token of [
  'ExpiresByType text/html "access plus 0 seconds"',
  'ExpiresByType text/css "access plus 1 hour"',
  'ExpiresByType application/javascript "access plus 1 hour"',
  'ExpiresByType application/manifest+json "access plus 1 hour"',
  'ExpiresByType image/png "access plus 7 days"',
  'ExpiresByType image/svg+xml "access plus 7 days"',
  'ExpiresByType image/webp "access plus 7 days"',
  'ExpiresByType image/avif "access plus 7 days"',
  'Header set Cache-Control "no-cache, must-revalidate"'
]){
  if(!htaccess.includes(token)) fail(".htaccess","missing cache policy token: "+token);
}
for(const [oldPath,newPath] of [["classic-room","classic-room.html"],["club-room","club-room.html"],["premium-room","premium-room.html"]]){
  if(!htaccess.includes(`RewriteRule ^room/${oldPath}/?$ /${newPath} [R=301,L]`)) fail(".htaccess",`legacy ${oldPath} 301 redirect missing`);
}
if(!htaccess.includes("RewriteRule ^facilities/?$ /facilities.html [R=301,L]")) fail(".htaccess","legacy Facilities 301 redirect missing");

// Original logo guard: keep the current first-party Hotel Vastu logo semantic and site-wide.
if(!exists("images/branding/hotel-vastu-logo.png")) fail("branding","missing refined original Hotel Vastu logo");
const logoCss=read("css/layout.css");
if(logoCss.includes(".brand::before{")) fail("branding","legacy generated V mark must not return");
if(logoCss.includes('background:url("../images/branding/hotel-vastu-logo.png")')) fail("branding","logo must not regress to CSS background rendering");

// Hero asset resolution guard: CSS custom-property URLs resolve from the stylesheet, so use root-absolute image paths.
for(const file of htmlFiles){
  const html=read(file);
  if(/--hero-image:url\(['"]?images\//.test(html)) fail(file,"visual hero image URL must be root-absolute /images/... to avoid /css/images 404s");
}

// Production CSS bundle guard: Lighthouse-audited public pages serve one render-blocking CSS bundle.
const cssBundleSources=["css/variables.css","css/base.css","css/components.css","css/layout.css","css/responsive.css"];
const expectedCssBundle=cssBundleSources.map(file=>`/* ${file} */\n${read(file).trim()}`).join("\n\n")+"\n";
if(!exists("css/site.css")) fail("css/site.css","production CSS bundle missing");
else if(read("css/site.css")!==expectedCssBundle) fail("css/site.css","bundle is stale; run npm run build:css");
const bundledPublicPages=["index.html","rooms.html","classic-room.html","club-room.html","premium-room.html","facilities.html","restaurant.html","gallery.html","about.html","contact.html","hotel-near-rps-more.html","hotel-near-danapur-railway-station.html"];
for(const file of bundledPublicPages){
  const html=read(file);
  if(!html.includes('href="css/site.css"')) fail(file,"public page must load css/site.css");
  if(html.includes('href="css/variables.css"')||html.includes('href="css/base.css"')||html.includes('href="css/components.css"')||html.includes('href="css/layout.css"')||html.includes('href="css/responsive.css"')) fail(file,"public page must not load split CSS in production");
}

// Honest direct-booking guard.
const contactPage=read("contact.html");
const bookingSource=read("js/booking.js");
const bookingFormMarkup=contactPage.match(/<form[^>]*data-booking-form[^>]*>[\s\S]*?<\/form>/i)?.[0]||"";
if(!bookingFormMarkup.includes('action="contact.html#booking"')||!bookingFormMarkup.includes('method="get"')) fail("contact.html","stay planner must have an explicit same-page no-PII fallback action");
if(/\bname=["'][^"']+["']/i.test(bookingFormMarkup)) fail("contact.html","browser-only stay planner controls must not expose named fields to native submission");
if(bookingSource.includes("new FormData(form)")) fail("js/booking.js","stay planner must read values locally by element id, not depend on named form submission fields");
for(const token of ["Stay planner","does not send or store personal details","Prepare booking details"]){
  if(!contactPage.includes(token)) fail("contact.html","missing truthful stay-planner token "+token);
}
for(const token of ["Nothing has been sent or stored","tel:+918002007466","Call hotel to book"]){
  if(!bookingSource.includes(token)) fail("js/booking.js","missing direct-booking completion token "+token);
}
if(!read("js/main.js").includes('["Book / Enquire","Send enquiry","Send an enquiry","Enquire now"]')) fail("js/main.js","shared stay-planner CTA normalization missing");
const homeBookingSource=read("js/home-booking.js");
const homeBookingMarkup=read("index.html").match(/<form[^>]*data-home-booking[^>]*>[\s\S]*?<\/form>/i)?.[0]||"";
if(!homeBookingMarkup.includes('action="contact.html#booking"')||!homeBookingMarkup.includes('method="get"')) fail("index.html","homepage stay planner must have an explicit contact-page no-query fallback");
if(/\bname=["'][^"']+["']/i.test(homeBookingMarkup)) fail("index.html","homepage stay planner controls must not expose named fields to native submission");
if(homeBookingSource.includes("new FormData(form)")) fail("js/home-booking.js","homepage stay planner must assemble its local draft without native submission fields");
const homeTruth=read("index.html");
if(homeTruth.includes("booking enquiry form")) fail("index.html","FAQ structured data must describe the local stay planner");
if(homeTruth.includes("security support")) fail("index.html","unverified security-support claim must not appear");
if(read("facilities.html").includes("send an enquiry")) fail("facilities.html","CTA must not imply the local planner transmits an enquiry");
if(read("restaurant.html").includes(">Ask the hotel</a>")) fail("restaurant.html","planner CTA must not imply a message is sent");
for(const file of htmlFiles){
  const html=read(file);
  if(/<a[^>]*href=["']contact\.html#booking["'][^>]*>\s*(?:Book \/ Enquire|Send enquiry|Send an enquiry|Enquire now|Ask the hotel|Contact hotel|Booking enquiry)\s*<\/a>/i.test(html)){
    fail(file,"stay-planner CTA must not imply transmission or direct contact");
  }
}
if(read("privacy.html").includes("booking enquiry form")) fail("privacy.html","privacy copy must describe the browser-only stay planner");
if(read("js/booking.js").includes("Hotel Vastu Premium booking enquiry")) fail("js/booking.js","prepared summary must be labelled booking details, not a transmitted enquiry");
const homeSitemapBlock=sitemap.match(/<url>\s*<loc>https:\/\/hotelvastu\.com\/<\/loc>[\s\S]*?<\/url>/)?.[0]||"";
if(!homeSitemapBlock.includes("https://hotelvastu.com/images/hotel/home-banner-1.webp")) fail("sitemap.xml","homepage image mapping must include the actual desktop hero");
if(homeSitemapBlock.includes("https://hotelvastu.com/images/hotel/hero.webp")) fail("sitemap.xml","homepage image mapping must not use unrelated hero.webp");


// Luxury design system guard: every page must keep the premium system and smooth scrolling.
const premiumHome=read("index.html");
for(const token of ["data-home-booking","js/home-booking.js","availability-bar","hero-actions","facility-showcase"]){
  if(!premiumHome.includes(token)) fail("index.html","missing luxury homepage token "+token);
}
if(!exists("js/home-booking.js")) fail("index.html","missing js/home-booking.js");
for(const file of ["classic-room.html","club-room.html","premium-room.html"]){
  const html=read(file);
  for(const token of ["room-detail-grid","room-highlights","room-booking-panel","room-photo-mosaic"]){
    if(!html.includes(token)) fail(file,"missing editorial room token "+token);
  }
}
for(const file of htmlFiles){
  const html=read(file);
  if(file!=="index.html"&&!html.includes("visual-page-hero")) fail(file,"missing visual luxury page hero");
  if(!html.includes('href="facilities.html">Facilities</a>')) fail(file,"missing Facilities navigation");
}
const combinedCss=read("css/base.css")+"\n"+read("css/layout.css")+"\n"+read("css/components.css");
for(const token of [".visual-page-hero{",".availability-bar{",".lux-reveal{","scroll-padding-top","scroll-margin-top",".site-header.is-scrolled"]){
  if(!combinedCss.includes(token)) fail("design-system","missing luxury/smooth-scroll CSS token "+token);
}
// Guest-facing UX regression guard: keep the booking journey premium, truthful and mobile-friendly.
const uxPublicPages=["index.html","rooms.html","classic-room.html","club-room.html","premium-room.html","facilities.html","restaurant.html","gallery.html","about.html","contact.html"];
const bannedGuestPhrases=["current website","existing website","first-party","confirmed public baseline","dining photo pending","unverified claims"];
for(const file of uxPublicPages){
  const html=read(file);
  const visibleish=html.replace(/<script[\s\S]*?<\/script>/gi," ");
  for(const phrase of bannedGuestPhrases){
    if(visibleish.toLowerCase().includes(phrase)) fail(file,"guest-facing audit/development wording must not return: "+phrase);
  }
}
const homeUx=read("index.html");
const roomSectionPos=homeUx.indexOf(">Accommodation</p>");
const transportSectionPos=homeUx.indexOf("data-nearby-transport");
if(roomSectionPos<0||transportSectionPos<0||roomSectionPos>transportSectionPos) fail("index.html","room discovery must appear before nearby-transport content");
if(homeUx.includes(">Check stay</button>")) fail("index.html","homepage planner CTA must not imply live inventory checking");
if(!homeUx.includes(">Prepare stay details</button>")) fail("index.html","homepage planner needs the truthful Prepare stay details CTA");
if(read("rooms.html").includes('href="contact.html#booking">Check availability</a>')) fail("rooms.html","room browse CTA must not imply live availability checking");
if(read("facilities.html").includes('href="contact.html#booking">Plan an event</a>')) fail("facilities.html","event CTA must use an event-specific direct contact path");
const responsiveCss=read("css/responsive.css");
if(!responsiveCss.includes('url("../images/hotel/home-banner-1.webp") center/cover')) fail("css/responsive.css","mobile homepage hero must retain real hotel photography");
if(!responsiveCss.includes(".hero-actions{display:none}")) fail("css/responsive.css","mobile hero utility actions must not duplicate the fixed quick-action bar");
const uxMainJs=read("js/main.js");
if(!uxMainJs.includes('"Close navigation"')||!uxMainJs.includes('button.textContent=open?"×":"☰"')) fail("js/main.js","mobile navigation button must expose synchronized open/close state");

// Booking timezone guard: date-only hotel stays must never use UTC ISO conversion.
for(const file of ["js/booking.js","js/home-booking.js"]){
  const source=read(file);
  if(source.includes("toISOString()")) fail(file,"date validation must use local calendar dates, not UTC toISOString()");
  for(const token of ["getFullYear()","getMonth()+1","getDate()","setDate("]){
    if(!source.includes(token)) fail(file,"missing local-calendar date token "+token);
  }
}

// Muted text contrast guard: #70665e is AA-safe on the cream surface used by .lead.
if(!read("css/variables.css").includes("--muted:#70665e;")) fail("css/variables.css","muted text color must keep AA contrast on cream backgrounds");

// Accessibility contrast guard for the primary CTA.
const componentCss=read("css/components.css");
if(!componentCss.includes("linear-gradient(135deg,#8a6338,#76502a)")||!componentCss.includes("color:#fff")) fail("css/components.css","primary CTA must keep the WCAG-AA dark-gold treatment");

const mainJs=read("js/main.js");
for(const token of ["scrollIntoView","prefers-reduced-motion","requestAnimationFrame","is-scrolled","IntersectionObserver","lux-reveal","is-visible"]){
  if(!mainJs.includes(token)) fail("js/main.js","missing smooth-scroll token "+token);
}
if(!exists("scripts/browser-check.mjs")) fail("browser-qa","browser QA script missing");
if(!exists(".github/workflows/browser-qa.yml")) fail("browser-qa","browser QA workflow missing");
for(const file of ["js/main.js","js/booking.js","js/gallery.js","js/home-booking.js"]){
  try{new Function(read(file));}catch(e){fail(file,"JavaScript syntax error: "+e.message);}
}

// Current room inventory guard: first-party Hotel Vastu menu is Classic / Club / Premium.
for(const file of ["classic-room.html","club-room.html","premium-room.html"]){
  if(!exists(file)) fail("rooms","missing current room page "+file);
  if(!sitemapFiles.has(file)) fail("sitemap.xml","missing current room "+file);
}
if(sitemapFiles.has("luxury-room.html")) fail("sitemap.xml","non-current Luxury Room must not be indexed");
if(read("luxury-room.html").includes('"@type":"HotelRoom"')) fail("luxury-room.html","legacy noindex room must not publish HotelRoom structured data");

// Current Facilities guard: preserve the live /facilities route and its confirmed photos.
if(!exists("facilities.html")) fail("facilities","missing facilities.html");
if(!sitemapFiles.has("facilities.html")) fail("sitemap.xml","missing facilities.html");
for(const asset of ["images/facilities/banquet-events.webp","images/facilities/corporate-stay.webp"]){
  if(!exists(asset)) fail("facilities","missing current-site asset "+asset);
}
const facilities=read("facilities.html");
for(const asset of ["images/facilities/banquet-events.webp","images/facilities/corporate-stay.webp"]){
  if(!facilities.includes(asset)) fail("facilities.html","missing current-site photo "+asset);
}

// Gallery experience guard: the complete verified first-party hotel photo set must stay wired.
const gallery=read("gallery.html");
const firstPartyPhotoAssets=["images/hotel/home-banner-1.webp","images/hotel/home-banner-2.webp","images/hotel/home-about-1.webp","images/hotel/home-about-2.webp","images/hotel/home-video-cover.webp","images/hotel/about.webp","images/hotel/about-room.webp","images/hotel/about-interior.webp","images/hotel/about-facility.webp","images/hotel/contact.webp","images/hotel/hero.webp","images/rooms/classic-room.webp","images/rooms/classic-room-1.webp","images/rooms/classic-room-2.webp","images/rooms/club-banner.webp","images/rooms/club-room.webp","images/rooms/club-room-1.webp","images/rooms/club-room-2.webp","images/rooms/premium-banner.webp","images/rooms/premium-room.webp","images/rooms/premium-room-1.webp","images/rooms/premium-room-2.webp","images/facilities/banquet-events.webp","images/facilities/corporate-stay.webp"];
for(const token of ["data-gallery-grid","data-gallery-dialog","js/gallery.js",'data-gallery-filter="hotel"']){
  if(!gallery.includes(token)) fail("gallery.html","missing gallery token "+token);
}
for(const match of gallery.matchAll(/<button class="gallery-tile gallery-button"[\s\S]*?<img\b[^>]*\balt=["']([^"']*)["'][^>]*>/gi)){
  if(match[1]!=="") fail("gallery.html","captioned gallery thumbnails must use empty alt to avoid duplicate accessible text");
}
for(const asset of firstPartyPhotoAssets){
  if(!exists(asset)) fail("gallery.html","missing first-party photo asset "+asset);
  if(!gallery.includes(asset)) fail("gallery.html","first-party hotel photo missing from complete gallery: "+asset);
}
if(!exists("js/gallery.js")) fail("gallery.html","missing js/gallery.js");
if(!sitemap.includes('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"')) fail("sitemap.xml","image namespace missing");
for(const asset of firstPartyPhotoAssets){
  const imageUrl="https://hotelvastu.com/"+asset;
  if(!sitemap.includes(imageUrl)) fail("sitemap.xml","missing image sitemap URL "+imageUrl);
}
if(!read("club-room.html").includes("/images/rooms/club-banner.webp")) fail("club-room.html","original Club Room banner must remain the hero");
for(const file of ["contact.html","hotel-near-rps-more.html","hotel-near-danapur-railway-station.html"]){
  if(read(file).includes("temp-visual-hero")) fail(file,"verified first-party photo is available; representative hero must not return");
}

// Temporary representative visual guard: keep labels only where no first-party photo is available.
for(const [page,asset] of [["deluxe-room.html","images/temp/room-temp.svg"],["luxury-room.html","images/temp/room-temp.svg"],["suite-room.html","images/temp/room-temp.svg"]]){
  if(!exists(asset)) fail(page,"missing temporary visual "+asset);
  const html=read(page);
  if(!html.includes(asset)) fail(page,"temporary visual not wired: "+asset);
  if(!html.includes("temp-visual")) fail(page,"temporary visual must use temp-visual badge");
  if(!html.includes("temp-visual-hero")) fail(page,"temporary marketing page must keep labelled representative hero");
}

const restaurantPage=read("restaurant.html");
if(restaurantPage.includes("images/temp/restaurant-temp.svg")||restaurantPage.includes("temp-visual-hero")) fail("restaurant.html","restaurant must not use a fake representative visual when hotel photography is available");
if(!restaurantPage.includes("images/hotel/about-interior.webp")) fail("restaurant.html","restaurant hotel-interior hero treatment missing");
if(restaurantPage.includes("context-photo-hero")) fail("restaurant.html","guest-facing restaurant hero must not expose an internal photo-status badge");
if(exists("images/temp/restaurant-temp.svg")) fail("assets","unused restaurant temporary illustration must stay removed");

// Dynamic image migration guard: do not pin Vite content hashes in maintenance tooling.
const migrationSource=read("scripts/migrate-current-images.mjs");
if(!migrationSource.includes("resolveUnique(")||!migrationSource.includes("fetchLiveBundle(")) fail("scripts/migrate-current-images.mjs","live image migration must resolve the current bundle dynamically");
for(const staleHash of ["DzljoJNY","KKyyZ1OO","B3IF7IY_","BfCtKmSj"]){
  if(migrationSource.includes(staleHash)) fail("scripts/migrate-current-images.mjs","hardcoded live asset hash must not return: "+staleHash);
}

// Maintenance workflow guard: source-mutating jobs must be manual-only.
for(const file of [".github/workflows/final-polish.yml",".github/workflows/migrate-current-photos.yml",".github/workflows/discover-live-images.yml",".github/workflows/audit-live-images.yml",".github/workflows/audit-rendered-live-photos.yml",".github/workflows/migrate-rendered-hotel-photos.yml"]){
  const workflow=read(file);
  if(!workflow.includes("workflow_dispatch:")) fail(file,"manual workflow_dispatch trigger missing");
  if(/\n\s*push:\s*\n/.test(workflow)) fail(file,"maintenance workflow must not auto-run on push");
}
if(!read("scripts/discover-live-images.mjs").includes("index-[^/]+\\.js")) fail("scripts/discover-live-images.mjs","live bundle discovery must resolve the current hashed entry dynamically");
if(!exists("scripts/migrate-rendered-hotel-photos.mjs")) fail("photo-migration","rendered first-party photo migration script missing");

// Nearby transport content guard: distances are approximate and must keep live-directions links.
const nearbyTransportPages=["index.html","hotel-near-rps-more.html","hotel-near-danapur-railway-station.html"];
const nearbyTransportTruth=[
  ["Patna Junction","Approx. 10–12 km","ChIJ86aXgmhY7TkRS__VL8hYJXs"],
  ["Danapur Railway Station","Approx. 3.7 km drive","ChIJK5DaC_NX7TkR6_sOqLXWKoU"],
  ["Patliputra Junction","Approx. 4–5 km","ChIJ07q89atX7TkRjhz6JA2rmwY"],
  ["Patna Airport","Approx. 7.4 km","ChIJVTWh0OdX7TkR1QcP-S7TAQk"],
  ["Phulwari Sharif Railway Station","Approx. 6–8 km","ChIJW0osANxX7TkR1HJwwcae2IQ"]
];
for(const file of nearbyTransportPages){
  const html=read(file);
  if(!html.includes("data-nearby-transport")) fail(file,"nearby transport section missing");
  const nearbySection=html.match(/<section class="section" data-nearby-transport>[\s\S]*?<\/section>/i)?.[0]||"";
  const nearbyCards=(nearbySection.match(/<article class="card card-body">/g)||[]).length;
  if(nearbyCards!==5) fail(file,"nearby transport card count must stay exactly 5; found "+nearbyCards);
  if(!html.includes("Actual route and journey time can vary")) fail(file,"nearby transport variability note missing");
  for(const [label,distance,placeId] of nearbyTransportTruth){
    if(!html.includes(label)) fail(file,"nearby transport label missing: "+label);
    if(!html.includes(distance)) fail(file,"nearby transport distance missing: "+distance);
    if(!html.includes(placeId)) fail(file,"nearby transport directions place ID missing: "+placeId);
  }
}
for(const question of [
  "How far is Patna Junction from Hotel Vastu Premium?",
  "How far is Danapur Railway Station from Hotel Vastu Premium?",
  "How far is Patliputra Junction from Hotel Vastu Premium?",
  "How far is Patna Airport from Hotel Vastu Premium?",
  "How far is Phulwari Sharif Railway Station from Hotel Vastu Premium?"
]){
  if(!read("index.html").includes(question)) fail("index.html","nearby transport FAQ missing: "+question);
}

// Content hierarchy guard for key indexable browse/local pages.
for(const file of ["rooms.html","gallery.html","hotel-near-rps-more.html","hotel-near-danapur-railway-station.html"]){
  const html=read(file);
  const h2=(html.match(/<h2\b/gi)||[]).length;
  if(h2<1) fail(file,"expected at least one H2 for useful content hierarchy");
}

// Dead asset guard.
for(const asset of ["images/hotel/hero.svg","images/hotel/exterior.svg","images/rooms/classic-room.svg","images/rooms/luxury-room.svg","images/temp/reception-temp.svg","images/temp/exterior-temp.svg","images/temp/restaurant-temp.svg"]){
  if(exists(asset)) fail("assets","unused legacy asset must stay removed: "+asset);
}

// Documentation consistency guard.
const readme=read("README.md");
if(readme.includes("\\n\\n### Static QA")) fail("README.md","literal escaped newlines remain in Static QA section");
if(!readme.includes("hotel-vastu-icon-192.png")||!readme.includes("hotel-vastu-icon-512.png")) fail("README.md","current branded icons are undocumented");
const deployment=read("DEPLOYMENT.md");
if(deployment.includes("images/ after original hotel photos are added")) fail("DEPLOYMENT.md","stale pre-migration image instruction remains");
if(!deployment.includes("Cache behavior")) fail("DEPLOYMENT.md","cache behavior section missing");
if(deployment.includes("favicon.svg")) fail("DEPLOYMENT.md","stale favicon.svg deployment instruction must not return");
if(!deployment.includes("hotel-vastu-icon-192.png")||!deployment.includes("hotel-vastu-icon-512.png")) fail("DEPLOYMENT.md","current branded icon deployment instructions missing");

const home=read("index.html");
for(const room of ["classic-room.html","club-room.html","premium-room.html"]){
  if(!home.includes(`href="${room}"`)) fail("index.html","missing current room link "+room);
}
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
