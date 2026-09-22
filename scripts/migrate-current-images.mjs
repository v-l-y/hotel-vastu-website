import fs from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const base="https://hotelvastu.com/assets/images/room/";
const jobs=[
  ["classic-banner.jpg","images/hotel/hero.webp",1600,900,86],
  ["classic-room.jpg","images/rooms/classic-room.webp",1200,800,86],
  ["classic-room-1.jpg","images/rooms/classic-room-1.webp",1200,800,84],
  ["classic-room-2.jpg","images/rooms/classic-room-2.webp",1200,800,84],
  ["club-banner.jpg","images/rooms/club-banner.webp",1600,900,86],
  ["club-room.jpg","images/rooms/club-room.webp",1200,800,86],
  ["club-room-1.jpg","images/rooms/club-room-1.webp",1200,800,84],
  ["club-room-2.jpg","images/rooms/club-room-2.webp",1200,800,84],
  ["premium-banner.jpg","images/rooms/premium-banner.webp",1600,900,86],
  ["premium-room.jpg","images/rooms/premium-room.webp",1200,800,86],
  ["premium-room-1.jpg","images/rooms/premium-room-1.webp",1200,800,84],
  ["premium-room-2.jpg","images/rooms/premium-room-2.webp",1200,800,84]
];

async function download(name){
  const url=base+name;
  const res=await fetch(url,{headers:{"user-agent":"HotelVastuImageMigration/1.0"}});
  if(!res.ok)throw new Error(`Failed ${res.status} ${url}`);
  const type=res.headers.get("content-type")||"";
  if(!type.startsWith("image/"))throw new Error(`Not an image: ${url} (${type})`);
  return Buffer.from(await res.arrayBuffer());
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
  const bytes=await download(source);
  const original=await sharp(bytes).metadata();
  console.log(" source",original.width+"x"+original.height,original.format,bytes.length+" bytes");
  await refine(bytes,out,w,h,q);
  const stat=await fs.stat(out);
  console.log(" output",w+"x"+h,"webp",stat.size+" bytes");
}

const hero=await fs.readFile("images/hotel/hero.webp");
await sharp(hero)
  .resize(1200,630,{fit:"cover",position:"centre"})
  .webp({quality:86,smartSubsample:true,effort:6})
  .toFile("images/hotel/og-hotel-vastu.webp");

console.log("Image migration complete.");
