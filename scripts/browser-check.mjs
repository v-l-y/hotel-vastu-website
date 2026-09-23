import { chromium } from "playwright";
import AxeBuilder from "@axe-core/playwright";

const base=process.env.BASE_URL||"http://127.0.0.1:4173";
const pages=[
  "index.html","rooms.html","classic-room.html","club-room.html","premium-room.html",
  "facilities.html","restaurant.html","gallery.html","about.html","contact.html",
  "hotel-near-rps-more.html","hotel-near-danapur-railway-station.html",
  "privacy.html","booking-information.html","luxury-room.html","deluxe-room.html","suite-room.html","404.html"
];

const nearbyTransportPages=new Set(["index.html","hotel-near-rps-more.html","hotel-near-danapur-railway-station.html"]);
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
    if(nearbyTransportPages.has(file)){
      const transport=page.locator("[data-nearby-transport]");
      const cardCount=await transport.locator("article.card").count();
      if(cardCount!==5)errors.push("nearby transport card count "+cardCount+" != 5");
      const directions=transport.getByRole("link",{name:"Directions"});
      const directionCount=await directions.count();
      if(directionCount!==5)errors.push("nearby transport directions link count "+directionCount+" != 5");
      for(let i=0;i<directionCount;i++){
        const link=directions.nth(i);
        const href=await link.getAttribute("href");
        const target=await link.getAttribute("target");
        const rel=await link.getAttribute("rel");
        if(!href?.startsWith("https://www.google.com/maps/dir/?api=1"))errors.push("nearby transport directions link "+(i+1)+" is not Google Maps directions");
        if(target!=="_blank"||!rel?.split(/\s+/).includes("noopener"))errors.push("nearby transport directions link "+(i+1)+" missing safe external-link attributes");
      }
    }
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
const revealCount=await home.locator(".lux-reveal").count();
if(revealCount<1)failures.push("scroll reveal targets were not initialized");
else{
  const revealTarget=home.locator(".lux-reveal").last();
  await revealTarget.scrollIntoViewIfNeeded();
  try{
    await revealTarget.evaluate(el=>new Promise((resolve,reject)=>{
      const started=performance.now();
      const check=()=>{
        if(el.classList.contains("is-visible"))return resolve(true);
        if(performance.now()-started>1800)return reject(new Error("timeout"));
        requestAnimationFrame(check);
      };
      check();
    }));
  }catch{
    failures.push("scroll reveal target did not become visible after entering viewport");
  }
}
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

const contactPlanner=await bookingContext.newPage();
await contactPlanner.goto(`${base}/contact.html#booking`,{waitUntil:"networkidle"});
await contactPlanner.locator("#name").fill("Test Guest");
await contactPlanner.locator("#phone").fill("9999999999");
const plannerToday=await contactPlanner.evaluate(()=>{const d=new Date();const p=n=>String(n).padStart(2,"0");return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`});
await contactPlanner.locator("#checkin").fill(plannerToday);
await contactPlanner.locator("#checkin").dispatchEvent("change");
const plannerCheckout=await contactPlanner.locator("#checkout").getAttribute("min");
await contactPlanner.locator("#checkout").fill(plannerCheckout);
await contactPlanner.getByRole("button",{name:"Prepare booking details"}).click();
const plannerStatus=contactPlanner.locator("[data-form-status]");
if(await plannerStatus.isHidden())failures.push("booking planner did not reveal the prepared summary");
const plannerText=await plannerStatus.textContent();
if(!plannerText?.includes("Nothing has been sent or stored"))failures.push("booking planner transmission disclaimer missing");
if(!plannerText?.includes("Call hotel to book"))failures.push("booking planner direct-call completion action missing");
await contactPlanner.close();

const noJsContext=await browser.newContext({viewport:{width:1280,height:900},javaScriptEnabled:false});
const noJsPlanner=await noJsContext.newPage();
await noJsPlanner.goto(`${base}/contact.html#booking`,{waitUntil:"networkidle"});
await noJsPlanner.locator("#name").fill("Privacy Test Guest");
await noJsPlanner.locator("#phone").fill("9999999999");
await noJsPlanner.locator("#checkin").fill("2026-09-23");
await noJsPlanner.locator("#checkout").fill("2026-09-24");
await noJsPlanner.locator("#guests").fill("2");
await Promise.all([
  noJsPlanner.waitForNavigation({waitUntil:"networkidle"}),
  noJsPlanner.getByRole("button",{name:"Prepare booking details"}).click(),
]);
const noJsUrl=new URL(noJsPlanner.url());
if(noJsUrl.search)failures.push(`booking privacy: JavaScript-disabled fallback leaked form data into URL query ${noJsUrl.search}`);
if(noJsUrl.hash!=="#booking")failures.push(`booking privacy: JavaScript-disabled fallback did not return to #booking (${noJsPlanner.url()})`);
await noJsContext.close();

const mobile=await bookingContext.newPage();
await mobile.setViewportSize({width:390,height:844});
await mobile.goto(`${base}/index.html`,{waitUntil:"networkidle"});
if((await mobile.locator('a[href="contact.html#booking"]').last().textContent())?.trim()!=="Plan your stay")failures.push("shared booking CTA did not normalize to Plan your stay");
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
console.log("Browser QA passed for desktop/mobile pages, WCAG serious/critical checks, scroll reveal, booking dates, nav and gallery.");
