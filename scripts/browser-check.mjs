import { chromium } from "playwright";
import AxeBuilder from "@axe-core/playwright";

const base=process.env.BASE_URL||"http://127.0.0.1:4173";
const pages=[
  "index.html","rooms.html","classic-room.html","club-room.html","premium-room.html",
  "facilities.html","restaurant.html","gallery.html","about.html","contact.html",
  "hotel-near-rps-more.html","hotel-near-danapur-railway-station.html",
  "privacy.html","booking-information.html","luxury-room.html","deluxe-room.html","suite-room.html","404.html"
];

const failures=[];
const browser=await chromium.launch({headless:true});

async function reviewContext(label,options){
  const context=await browser.newContext({...options,timezoneId:"Asia/Kolkata"});
  for(const file of pages){
    const page=await context.newPage();
    const errors=[];
    page.on("pageerror",error=>errors.push("pageerror: "+error.message));
    page.on("console",msg=>{if(msg.type()==="error")errors.push("console: "+msg.text())});
    page.on("requestfailed",req=>errors.push("request failed: "+req.url()+" "+(req.failure()?.errorText||"")));page.on("response",res=>{if(res.status()>=400)errors.push(`HTTP ${res.status()}: ${res.url()}`)});
    const response=await page.goto(`${base}/${file}`,{waitUntil:"networkidle"});
    if(!response?.ok())errors.push("HTTP "+(response?.status()??"no response"));
    const overflow=await page.evaluate(()=>document.documentElement.scrollWidth>window.innerWidth+1);
    if(overflow)errors.push("horizontal overflow");
    await page.evaluate(()=>document.querySelectorAll(".lux-reveal").forEach(el=>el.classList.add("is-visible")));await page.waitForTimeout(850);const results=await new AxeBuilder({page}).withTags(["wcag2a","wcag2aa","wcag21a","wcag21aa"]).analyze();
    for(const violation of results.violations){
      if(["serious","critical"].includes(violation.impact||"")){
        const nodes=violation.nodes.slice(0,8).map(node=>`${node.target.join(" ")} => ${node.failureSummary||""}`).join(" || ");errors.push(`axe ${violation.id}: ${violation.help} (${violation.nodes.length}) ${nodes}`);
      }
    }
    if(errors.length)failures.push(`${label} ${file}: ${errors.join(" | ")}`);
    await page.close();
  }
  await context.close();
}

await reviewContext("desktop",{viewport:{width:1440,height:1000}});
await reviewContext("mobile",{viewport:{width:390,height:844},isMobile:true,hasTouch:true});

const bookingContext=await browser.newContext({viewport:{width:1280,height:900},timezoneId:"Asia/Kolkata"});
const home=await bookingContext.newPage();
await home.goto(`${base}/index.html`,{waitUntil:"networkidle"});
const dateState=await home.evaluate(()=>{
  const pad=n=>String(n).padStart(2,"0");
  const fmt=d=>`${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  const checkin=document.getElementById("home-checkin");
  const checkout=document.getElementById("home-checkout");
  return {today:fmt(new Date()),checkinMin:checkin?.min||"",checkoutMin:checkout?.min||""};
});
if(dateState.checkinMin!==dateState.today)failures.push(`booking: check-in min ${dateState.checkinMin} != local today ${dateState.today}`);
await home.locator("#home-checkin").fill(dateState.today);
await home.locator("#home-checkin").dispatchEvent("change");
const nextState=await home.evaluate(()=>{
  const checkin=document.getElementById("home-checkin");
  const checkout=document.getElementById("home-checkout");
  const [y,m,d]=checkin.value.split("-").map(Number);
  const n=new Date(y,m-1,d); n.setDate(n.getDate()+1);
  const pad=v=>String(v).padStart(2,"0");
  return {expected:`${n.getFullYear()}-${pad(n.getMonth()+1)}-${pad(n.getDate())}`,actual:checkout.min};
});
if(nextState.actual!==nextState.expected)failures.push(`booking: checkout min ${nextState.actual} != next local day ${nextState.expected}`);

const mobile=await bookingContext.newPage();
await mobile.setViewportSize({width:390,height:844});
await mobile.goto(`${base}/index.html`,{waitUntil:"networkidle"});
await mobile.locator("[data-menu-button]").click();
if(!await mobile.locator("[data-nav-links]").evaluate(el=>el.classList.contains("open")))failures.push("mobile nav did not open");

const gallery=await bookingContext.newPage();
await gallery.goto(`${base}/gallery.html`,{waitUntil:"networkidle"});
await gallery.locator("[data-gallery-item]").first().click();
if(!await gallery.locator("[data-gallery-dialog]").evaluate(el=>el.open))failures.push("gallery dialog did not open");

await bookingContext.close();
await browser.close();

if(failures.length){
  console.error("Browser QA failed:");
  failures.forEach(x=>console.error("✗",x));
  process.exit(1);
}
console.log("Browser QA passed for desktop/mobile pages, WCAG serious/critical checks, booking dates, nav and gallery.");
