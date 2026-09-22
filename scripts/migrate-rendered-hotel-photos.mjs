import fs from "node:fs/promises";
import path from "node:path";
import { chromium } from "playwright";
import sharp from "sharp";

const base="https://hotelvastu.com";
const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});

async function renderedImage(pagePath,alt,index=0){
  const url=new URL(pagePath,base).href;
  await page.goto(url,{waitUntil:"networkidle",timeout:60000});
  const locator=page.locator(`img[alt="${alt.replaceAll('"','\\\"')}"]`);
  const count=await locator.count();
  if(count<=index)throw new Error(`Missing rendered image alt=${alt} index=${index} on ${url}; count=${count}`);
  const src=await locator.nth(index).evaluate(img=>img.currentSrc||img.src);
  if(!src.startsWith(base+"/assets/"))throw new Error(`Unexpected image source for ${alt}: ${src}`);
  return src;
}

const jobs=[
  [await renderedImage("/","banner",0),"images/hotel/home-banner-1.webp"],
  [await renderedImage("/","banner",1),"images/hotel/home-banner-2.webp"],
  [await renderedImage("/","Background"),"images/hotel/home-about-1.webp"],
  [await renderedImage("/","About"),"images/hotel/home-about-2.webp"],
  [await renderedImage("/","video"),"images/hotel/home-video-cover.webp"],
  [await renderedImage("/room/club-room","Room Banner"),"images/rooms/club-banner.webp"],
  [await renderedImage("/about","Hotel Room"),"images/hotel/about-room.webp"],
  [await renderedImage("/about","Hotel Interior"),"images/hotel/about-interior.webp"],
  [await renderedImage("/about","facility"),"images/hotel/about-facility.webp"],
  [await renderedImage("/contact","contact"),"images/hotel/contact.webp"]
];

await browser.close();

async function migrate(url,out){
  const res=await fetch(url,{headers:{"user-agent":"HotelVastuRenderedPhotoMigration/1.0"}});
  if(!res.ok)throw new Error(`Failed ${res.status} ${url}`);
  const type=res.headers.get("content-type")||"";
  if(!type.startsWith("image/"))throw new Error(`Not an image: ${url} (${type})`);
  const bytes=Buffer.from(await res.arrayBuffer());
  const meta=await sharp(bytes).metadata();
  await fs.mkdir(path.dirname(out),{recursive:true});
  await sharp(bytes)
    .rotate()
    .resize({width:1600,withoutEnlargement:true})
    .modulate({brightness:1.01,saturation:1.025})
    .sharpen({sigma:0.45})
    .webp({quality:86,smartSubsample:true,effort:6})
    .toFile(out);
  const stat=await fs.stat(out);
  console.log("MIGRATED",url,"->",out,`${meta.width||0}x${meta.height||0}`,stat.size);
}

for(const [url,out] of jobs)await migrate(url,out);
console.log("Migrated",jobs.length,"rendered first-party Hotel Vastu photos.");
