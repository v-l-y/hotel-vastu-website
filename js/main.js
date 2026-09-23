/* HOTEL_VASTU_ICON_SYSTEM */
const HOTEL_ICONS={
home:'<path d="M3 11.5 12 4l9 7.5"/><path d="M5 10.5V20h14v-9.5"/><path d="M9 20v-6h6v6"/>',
bed:'<path d="M3 19v-8"/><path d="M21 19v-6a2 2 0 0 0-2-2H9a4 4 0 0 0-4 4v4"/><path d="M3 16h18"/><path d="M7 11V7h5a3 3 0 0 1 3 3v1"/>',
sparkles:'<path d="m12 3 1.2 3.3L16.5 7.5l-3.3 1.2L12 12l-1.2-3.3-3.3-1.2 3.3-1.2L12 3Z"/><path d="m19 13 .8 2.2L22 16l-2.2.8L19 19l-.8-2.2L16 16l2.2-.8L19 13Z"/><path d="m5 14 .7 1.8 1.8.7-1.8.7L5 19l-.7-1.8-1.8-.7 1.8-.7L5 14Z"/>',
utensils:'<path d="M6 3v7"/><path d="M3 3v4a3 3 0 0 0 6 0V3"/><path d="M6 10v11"/><path d="M16 3v18"/><path d="M16 3c3 0 5 2.5 5 5.5S19 14 16 14"/>',
image:'<rect x="3" y="4" width="18" height="16" rx="2"/><circle cx="9" cy="10" r="2"/><path d="m21 15-4.5-4.5L8 19"/>',
info:'<circle cx="12" cy="12" r="9"/><path d="M12 11v6"/><path d="M12 7h.01"/>',
phone:'<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.4 19.4 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1 1 .4 2 .7 2.9a2 2 0 0 1-.5 2.1L8.1 9.9a16 16 0 0 0 6 6l1.2-1.2a2 2 0 0 1 2.1-.5c.9.3 1.9.6 2.9.7a2 2 0 0 1 1.7 2Z"/>',
calendar:'<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M16 3v4M8 3v4M3 10h18"/><path d="m9 15 2 2 4-4"/>',
mapPin:'<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
wifi:'<path d="M5 12.6a10 10 0 0 1 14 0"/><path d="M8.5 16a5 5 0 0 1 7 0"/><path d="M12 20h.01"/>',
car:'<path d="M5 17h14"/><path d="m5 17-1-4 2-5h12l2 5-1 4"/><path d="M7 17v2M17 17v2"/><circle cx="8" cy="14" r="1"/><circle cx="16" cy="14" r="1"/>',
bell:'<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"/><path d="M10 21h4"/><path d="M12 3V1"/>',
roomService:'<path d="M3 18h18"/><path d="M5 18a7 7 0 0 1 14 0"/><path d="M12 8V5"/><circle cx="12" cy="4" r="1"/>',
users:'<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.9"/><path d="M16 3.1a4 4 0 0 1 0 7.8"/>',
train:'<rect x="5" y="3" width="14" height="14" rx="3"/><path d="M8 21l3-4M16 21l-3-4M8 8h8M8 12h.01M16 12h.01"/>',
plane:'<path d="M22 2 15 21l-4-8-8-4 19-7Z"/><path d="m11 13 4-4"/>',
arrowRight:'<path d="M5 12h14"/><path d="m13 6 6 6-6 6"/>',
menu:'<path d="M4 7h16M4 12h16M4 17h16"/>',
close:'<path d="m6 6 12 12M18 6 6 18"/>',
clock:'<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>'
};
function hotelIcon(name,className="ui-icon"){
 const svg=document.createElementNS("http://www.w3.org/2000/svg","svg");
 svg.setAttribute("viewBox","0 0 24 24");
 svg.setAttribute("fill","none");
 svg.setAttribute("stroke","currentColor");
 svg.setAttribute("stroke-width","1.8");
 svg.setAttribute("stroke-linecap","round");
 svg.setAttribute("stroke-linejoin","round");
 svg.setAttribute("aria-hidden","true");
 svg.setAttribute("focusable","false");
 svg.dataset.uiIcon=name;
 svg.classList.add(...className.split(" ").filter(Boolean));
 svg.innerHTML=HOTEL_ICONS[name]||HOTEL_ICONS.sparkles;
 return svg;
}
function prependHotelIcon(el,name,className="ui-icon"){
 if(!el||el.querySelector(":scope > svg[data-ui-icon]"))return;
 el.prepend(hotelIcon(name,className));
}
function ensureHotelIconStyles(){
 if(document.getElementById("hotel-icon-styles"))return;
 const style=document.createElement("style");
 style.id="hotel-icon-styles";
 style.textContent=`
 .ui-icon{width:1.08em;height:1.08em;flex:0 0 auto;display:inline-block;vertical-align:-.16em}
 .nav-links>a{display:inline-flex;align-items:center;gap:7px}
 .nav-links>a:not(.btn):not(.header-call)>.ui-icon{width:14px;height:14px;color:var(--gold-dark);opacity:.82}
 .header-call>.ui-icon,.btn>.ui-icon,.mobile-actions .ui-icon{width:16px;height:16px}
 .menu-btn .menu-icon{width:24px;height:24px;display:block}
 .fact-mark{width:44px;padding:0}
 .fact-mark>.ui-icon{width:19px;height:19px}
 .feature-icon{width:40px;height:40px;padding:9px;border:1px solid rgba(192,154,102,.22);border-radius:12px;background:#fbf5eb;color:var(--gold-dark);margin-bottom:16px}
 .dark-section .feature-icon{background:rgba(255,255,255,.08);border-color:rgba(255,255,255,.12);color:#e7cfaa}
 .transport-icon{width:36px;height:36px;padding:8px;border-radius:50%;background:#f8f0e4;color:var(--gold-dark);margin-bottom:12px}
 .breadcrumbs a:first-child{display:inline-flex;align-items:center;gap:5px}
 .breadcrumbs a:first-child>.ui-icon{width:13px;height:13px}
 .footer a[href^="tel:"]{display:inline-flex;align-items:center;gap:8px}
 .footer a[href^="tel:"]>.ui-icon{width:15px;height:15px;color:var(--gold-light)}
 .contact-card p>strong{display:inline-flex;align-items:center;gap:7px}
 .mobile-actions a{gap:7px}
 @media(max-width:900px){.nav-links>a:not(.btn):not(.header-call)>.ui-icon{width:16px;height:16px}}
 `;
 document.head.appendChild(style);
}
function applyHotelIcons(){
 ensureHotelIconStyles();
 const navMap={
  "index.html":"home","rooms.html":"bed","facilities.html":"sparkles","restaurant.html":"utensils",
  "gallery.html":"image","about.html":"info","contact.html":"phone"
 };
 document.querySelectorAll(".nav-links>a[href]").forEach(a=>{
  const href=(a.getAttribute("href")||"").split("#")[0].split("/").pop();
  const rawHref=a.getAttribute("href")||"";const icon=rawHref.includes("#booking")?"calendar":navMap[href];
  if(icon)prependHotelIcon(a,icon);
 });
 document.querySelectorAll("a.btn,button.btn").forEach(el=>{
  const href=(el.getAttribute("href")||"").toLowerCase();
  const text=(el.textContent||"").trim().toLowerCase();
  let icon=null;
  if(href.startsWith("tel:"))icon="phone";
  else if(href.includes("google.com/maps")||text.includes("direction"))icon="mapPin";
  else if(href.includes("#booking")||text.includes("plan your stay")||text.includes("prepare stay")||text.includes("prepare booking"))icon="calendar";
  else if(text.includes("view room"))icon="bed";
  else if(text.includes("facilit"))icon="sparkles";
  else if(text.includes("gallery"))icon="image";
  else if(text.includes("dining")||text.includes("restaurant"))icon="utensils";
  else if(text.includes("about"))icon="info";
  else if(text.includes("explore the location"))icon="mapPin";
  else if(text.includes("view")||text.includes("explore")||text.includes("details"))icon="arrowRight";
  if(icon)prependHotelIcon(el,icon);
 });
 const factMap={
  "free wi-fi":"wifi","private parking":"car","24-hour front desk":"bell",
  "room service":"roomService","family rooms":"users","rps nagar":"mapPin"
 };
 document.querySelectorAll(".quick-facts>div").forEach(item=>{
  const key=(item.querySelector("strong")?.textContent||"").trim().toLowerCase();
  const mark=item.querySelector(".fact-mark");
  if(mark&&factMap[key]){
   mark.textContent="";
   mark.appendChild(hotelIcon(factMap[key]));
  }
 });
 const featureMap=[
  [/wi-?fi|internet/,"wifi"],[/parking/,"car"],[/front desk|reception|24-hour/,"bell"],
  [/room service/,"roomService"],[/family/,"users"],[/restaurant|breakfast|dining/,"utensils"],
  [/location|rps|nearby/,"mapPin"],[/check.?in|check.?out|timing|hour/,"clock"],
  [/room|bed/,"bed"],[/event|banquet|facility|amenit/,"sparkles"]
 ];
 document.querySelectorAll(".amenity,.facility-text-card").forEach(card=>{
  if(card.querySelector(":scope > .feature-icon"))return;
  const text=(card.querySelector("strong")?.textContent||card.textContent||"").toLowerCase();
  const match=featureMap.find(([re])=>re.test(text));
  card.prepend(hotelIcon(match?.[1]||"sparkles","ui-icon feature-icon"));
 });
 document.querySelectorAll("[data-nearby-transport] .card").forEach(card=>{
  if(card.querySelector(":scope > .transport-icon"))return;
  const kind=(card.querySelector(".eyebrow")?.textContent||"").trim().toLowerCase();
  card.prepend(hotelIcon(kind.includes("air")?"plane":"train","ui-icon transport-icon"));
 });
 document.querySelectorAll(".breadcrumbs a:first-child").forEach(a=>prependHotelIcon(a,"home"));
 document.querySelectorAll('.footer a[href^="tel:"]').forEach(a=>prependHotelIcon(a,"phone"));
 document.querySelectorAll('.contact-card a[href^="tel:"]').forEach(a=>prependHotelIcon(a,"phone"));
 const phoneLabel=[...document.querySelectorAll(".contact-card p>strong")].find(el=>/phone/i.test(el.textContent||""));
 if(phoneLabel)prependHotelIcon(phoneLabel,"phone");
 document.querySelectorAll(".mobile-actions a").forEach(a=>{
  const href=(a.getAttribute("href")||"").toLowerCase();
  prependHotelIcon(a,href.startsWith("tel:")?"phone":href.includes("maps")?"mapPin":"calendar");
 });
}
document.addEventListener("DOMContentLoaded",()=>{if(!document.querySelector('link[rel="icon"][href$="favicon.svg"]')){const icon=document.createElement("link");icon.rel="icon";icon.type="image/svg+xml";icon.href="favicon.svg";document.head.appendChild(icon)}if(!document.querySelector('link[rel="apple-touch-icon"]')){const apple=document.createElement("link");apple.rel="apple-touch-icon";apple.href="images/branding/hotel-vastu-icon-192.png";document.head.appendChild(apple)}const button=document.querySelector("[data-menu-button]");const nav=document.querySelector("[data-nav-links]");const syncMenuButton=open=>{if(!button)return;button.setAttribute("aria-expanded",String(open));button.setAttribute("aria-label",open?"Close navigation":"Open navigation");button.replaceChildren(hotelIcon(open?"close":"menu","ui-icon menu-icon"))};const closeMenu=()=>{if(!button||!nav)return;nav.classList.remove("open");syncMenuButton(false)};if(button&&nav){syncMenuButton(false);const current=(location.pathname.split("/").pop()||"index.html").toLowerCase();nav.querySelectorAll('a[href]').forEach(link=>{const href=(link.getAttribute("href")||"").split("#")[0].toLowerCase();if(href===current)link.setAttribute("aria-current","page")});document.querySelectorAll('a[href="contact.html#booking"]').forEach(link=>{const label=link.textContent.trim();if(["Book / Enquire","Send enquiry","Send an enquiry","Enquire now"].includes(label))link.textContent="Plan your stay"});if(!nav.querySelector(".header-call")){const booking=nav.querySelector(".btn-primary");const call=document.createElement("a");call.className="header-call";call.href="tel:+918002007466";call.textContent="+91 80020 07466";call.setAttribute("aria-label","Call Hotel Vastu Premium at +91 80020 07466");booking?nav.insertBefore(call,booking):nav.appendChild(call)}button.addEventListener("click",()=>{const open=nav.classList.toggle("open");syncMenuButton(open)});nav.querySelectorAll("a").forEach(link=>link.addEventListener("click",closeMenu));document.addEventListener("keydown",event=>{if(event.key==="Escape"&&nav.classList.contains("open")){closeMenu();button.focus()}});document.addEventListener("click",event=>{if(nav.classList.contains("open")&&!nav.contains(event.target)&&!button.contains(event.target))closeMenu()});window.addEventListener("resize",()=>{if(window.innerWidth>900)closeMenu()})}document.querySelectorAll("[data-year]").forEach(el=>el.textContent=new Date().getFullYear());const reduced=window.matchMedia("(prefers-reduced-motion: reduce)").matches;document.querySelectorAll('a[href^="#"]').forEach(link=>link.addEventListener("click",event=>{const id=link.getAttribute("href");if(!id||id==="#")return;const target=document.querySelector(id);if(!target)return;event.preventDefault();target.scrollIntoView({behavior:reduced?"auto":"smooth",block:"start"});history.pushState(null,"",id)}));if(location.hash){const target=document.querySelector(location.hash);if(target)setTimeout(()=>target.scrollIntoView({behavior:reduced?"auto":"smooth",block:"start"}),80)}const header=document.querySelector(".site-header");if(header){let ticking=false;const syncHeader=()=>{header.classList.toggle("is-scrolled",window.scrollY>24);ticking=false};window.addEventListener("scroll",()=>{if(!ticking){requestAnimationFrame(syncHeader);ticking=true}},{passive:true});syncHeader()}const initScrollReveal=()=>{if(reduced||!("IntersectionObserver"in window))return;const selectors=["main>.container.quick-facts>div","main .section-heading-row","main .room-grid>.card","main .feature-grid>.card","main .amenity-grid>.amenity","main .facility-showcase>*","main .photo-story-grid>figure","main .two-col>*","main .cta-band","main .faq-list>details","main .room-detail-grid>*","main .room-photo-mosaic>figure","main .gallery-grid>.gallery-tile","main .content-grid>*","main .policy-card"];const targets=[...new Set(selectors.flatMap(selector=>[...document.querySelectorAll(selector)]))];if(!targets.length)return;const observer=new IntersectionObserver(entries=>{entries.forEach(entry=>{if(entry.isIntersecting){entry.target.classList.add("is-visible");observer.unobserve(entry.target)}})},{threshold:.1,rootMargin:"0px 0px -7% 0px"});targets.forEach((el,i)=>{const rect=el.getBoundingClientRect();if(rect.top<window.innerHeight*.86)return;el.classList.add("lux-reveal");el.style.setProperty("--reveal-delay",`${(i%4)*65}ms`);observer.observe(el)})};requestAnimationFrame(()=>requestAnimationFrame(initScrollReveal));if(!document.querySelector(".mobile-actions")){const bar=document.createElement("nav");bar.className="mobile-actions";bar.setAttribute("aria-label","Quick hotel actions");bar.innerHTML='<a href="tel:+918002007466">Call</a><a href="https://www.google.com/maps/search/?api=1&query=Hotel%20Vastu%20Premium&query_place_id=ChIJDxeajLFX7TkRZBzzwB6YHzg" target="_blank" rel="noopener">Directions</a><a href="contact.html#booking">Plan your stay</a>';document.body.appendChild(bar)}applyHotelIcons();});