import fs from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const site="https://hotelvastu.com/";

async function fetchLiveBundle(){
  const home=await fetch(site,{headers:{"user-agent":"HotelVastuImageMigration/3.0"}});
  if(!home.ok)throw new Error(`Homepage fetch failed: ${home.status}`);
  const html=await home.text();
  const scripts=[...html.matchAll(/<script[^>]+src=["']([^"']+)["']/gi)].map(m=>m[1]);
  const entry=scripts.find(src=>/\/assets\/index-[^/]+\.js(?:\?|$)/.test(src));
  if(!entry)throw new Error("Could not locate current Vite index bundle");
  const bundleUrl=new URL(entry,site).href;
  const res=await fetch(bundleUrl,{headers:{"user-agent":"HotelVastuImageMigration/3.0"}});
  if(!res.ok)throw new Error(`Bundle fetch failed: ${res.status}`);
  return {text:await res.text(),bundleUrl};
}

const {text:bundle,bundleUrl}=await fetchLiveBundle();
console.log("Resolved live bundle",bundleUrl);

function resolveUnique(regex,label){
  const matches=[...new Set([...bundle.matchAll(regex)].map(m=>m[1]))];
  if(matches.length!==1)throw new Error(`Expected one live asset for ${label}; found ${matches.length}: ${matches.join(", ")}`);
  console.log("Resolved",label,"->",matches[0]);
  return matches[0];
}

const sources={
  banquet:resolveUnique(/(?:\/assets\/)?(1-[A-Za-z0-9_-]+\.jpeg)/g,"Banquet & Events"),
  corporate:resolveUnique(/(?:\/assets\/)?(2-[A-Za-z0-9_-]+\.jpg)/g,"Corporate Stay"),
  about:resolveUnique(/(?:\/assets\/)?(about-main-[A-Za-z0-9_-]+\.webp)/g,"About"),
  classicBanner:resolveUnique(/(?:\/assets\/)?(classic-banner-[A-Za-z0-9_-]+\.jpg)/g,"Classic banner"),
  classic:resolveUnique(/(?:\/assets\/)?(classic-room-(?!1-|2-)[A-Za-z0-9_-]+\.jpg)/g,"Classic room"),
  classic1:resolveUnique(/(?:\/assets\/)?(classic-room-1-[A-Za-z0-9_-]+\.jpg)/g,"Classic room 1"),
  classic2:resolveUnique(/(?:\/assets\/)?(classic-room-2-[A-Za-z0-9_-]+\.jpg)/g,"Classic room 2"),
  club:resolveUnique(/(?:\/assets\/)?(club-room-(?!1-|2-)[A-Za-z0-9_-]+\.jpg)/g,"Club room"),
  club1:resolveUnique(/(?:\/assets\/)?(club-room-1-[A-Za-z0-9_-]+\.jpg)/g,"Club room 1"),
  club2:resolveUnique(/(?:\/assets\/)?(club-room-2-[A-Za-z0-9_-]+\.jpg)/g,"Club room 2"),
  premiumBanner:resolveUnique(/(?:\/assets\/)?(premium-banner-[A-Za-z0-9_-]+\.jpg)/g,"Premium banner"),
  premium:resolveUnique(/(?:\/assets\/)?(premium-room-(?!1-|2-)[A-Za-z0-9_-]+\.jpg)/g,"Premium room"),
  premium1:resolveUnique(/(?:\/assets\/)?(premium-room-1-[A-Za-z0-9_-]+\.jpg)/g,"Premium room 1"),
  premium2:resolveUnique(/(?:\/assets\/)?(premium-room-2-[A-Za-z0-9_-]+\.jpg)/g,"Premium room 2"),
  logo:resolveUnique(/(?:\/assets\/)?(logo-[A-Za-z0-9_-]+\.png)/g,"Hotel logo")
};

const jobs=[
  [sources.banquet,"images/facilities/banquet-events.webp",1200,800,86],
  [sources.corporate,"images/facilities/corporate-stay.webp",1200,800,86],
  [sources.about,"images/hotel/about.webp",1200,800,86],
  [sources.classicBanner,"images/hotel/hero.webp",1600,900,86],
  [sources.classic,"images/rooms/classic-room.webp",1200,800,86],
  [sources.classic1,"images/rooms/classic-room-1.webp",1200,800,84],
  [sources.classic2,"images/rooms/classic-room-2.webp",1200,800,84],
  [sources.club,"images/rooms/club-room.webp",1200,800,86],
  [sources.club1,"images/rooms/club-room-1.webp",1200,800,84],
  [sources.club2,"images/rooms/club-room-2.webp",1200,800,84],
  [sources.premiumBanner,"images/rooms/premium-banner.webp",1600,900,86],
  [sources.premium,"images/rooms/premium-room.webp",1200,800,86],
  [sources.premium1,"images/rooms/premium-room-1.webp",1200,800,84],
  [sources.premium2,"images/rooms/premium-room-2.webp",1200,800,84]
];

async function download(name){
  const url=new URL("assets/"+name,site).href;
  const res=await fetch(url,{headers:{"user-agent":"HotelVastuImageMigration/3.0"}});
  if(!res.ok)throw new Error(`Failed ${res.status} ${url}`);
  const type=res.headers.get("content-type")||"";
  if(!type.startsWith("image/"))throw new Error(`Not an image: ${url} (${type})`);
  return {bytes:Buffer.from(await res.arrayBuffer()),url};
}

async function refine(input,out,w,h,quality){
  await fs.mkdir(path.dirname(out),{recursive:true});
  await sharp(input)
    .rotate()
    .resize(w,h,{fit:"cover",position:"centre",withoutEnlargement:false})
    .modulate({brightness:1.01,saturation:1.035})
    .sharpen({sigma:0.65})
    .webp({quality,smartSubsample:true,effort:6})
    .toFile(out);
}

for(const [source,out,w,h,q] of jobs){
  console.log("Migrating",source,"->",out);
  const {bytes,url}=await download(source);
  const original=await sharp(bytes).metadata();
  console.log(" source",url,original.width+"x"+original.height,original.format,bytes.length+" bytes");
  await refine(bytes,out,w,h,q);
  const stat=await fs.stat(out);
  console.log(" output",w+"x"+h,"webp",stat.size+" bytes");
}

await sharp("images/hotel/hero.webp")
  .resize(1200,630,{fit:"cover",position:"centre"})
  .webp({quality:86,smartSubsample:true,effort:6})
  .toFile("images/hotel/og-hotel-vastu.webp");

const {bytes:logoBytes}=await download(sources.logo);
const meta=await sharp(logoBytes).metadata();
console.log("Migrating original logo",meta.width+"x"+meta.height,meta.format,logoBytes.length+" bytes");
await fs.mkdir("images/branding",{recursive:true});
await sharp(logoBytes)
  .rotate()
  .trim({background:{r:255,g:255,b:255,alpha:0}})
  .resize({width:520,withoutEnlargement:true})
  .sharpen({sigma:0.35})
  .png({compressionLevel:9,adaptiveFiltering:true})
  .toFile("images/branding/hotel-vastu-logo.png");

{
  const source="images/branding/hotel-vastu-logo.png";
  const bg={r:255,g:253,b:250,alpha:1};
  for(const size of [192,512]){
    const inner=Math.round(size*0.82);
    const resized=await sharp(source)
      .resize({width:inner,height:inner,fit:"contain",background:bg})
      .png()
      .toBuffer();
    await sharp({create:{width:size,height:size,channels:4,background:bg}})
      .composite([{input:resized,gravity:"centre"}])
      .png({compressionLevel:9,adaptiveFiltering:true})
      .toFile(`images/branding/hotel-vastu-icon-${size}.png`);
  }
}

console.log("Image migration complete.");
