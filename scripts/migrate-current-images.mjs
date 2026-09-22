import fs from "node:fs/promises";
import path from "node:path";
import sharp from "sharp";

const base="https://hotelvastu.com/assets/";
const jobs=[
  ["1-w7sk1Faz.jpeg","images/facilities/banquet-events.webp",1200,800,86],
  ["2-BSgCX3q9.jpg","images/facilities/corporate-stay.webp",1200,800,86],
  ["about-main-B4uPe_oU.webp","images/hotel/about.webp",1200,800,86],
  ["classic-banner-DzljoJNY.jpg","images/hotel/hero.webp",1600,900,86],
  ["classic-room-CvOVV3gC.jpg","images/rooms/classic-room.webp",1200,800,86],
  ["classic-room-1-HuwfF5oj.jpg","images/rooms/classic-room-1.webp",1200,800,84],
  ["classic-room-2-L97g_-BQ.jpg","images/rooms/classic-room-2.webp",1200,800,84],
  ["club-banner-KKyyZ1OO.jpg","images/rooms/club-banner.webp",1600,900,86],
  ["club-room-CTKNxkaI.jpg","images/rooms/club-room.webp",1200,800,86],
  ["club-room-1-Fh2f68TO.jpg","images/rooms/club-room-1.webp",1200,800,84],
  ["club-room-2-BB5k85GI.jpg","images/rooms/club-room-2.webp",1200,800,84],
  ["premium-banner-B3IF7IY_.jpg","images/rooms/premium-banner.webp",1600,900,86],
  ["premium-room-tGt7MRIw.jpg","images/rooms/premium-room.webp",1200,800,86],
  ["premium-room-1-DpxKacU9.jpg","images/rooms/premium-room-1.webp",1200,800,84],
  ["premium-room-2-DhAAT2aF.jpg","images/rooms/premium-room-2.webp",1200,800,84]
];

async function download(name){
  const url=base+name;
  const res=await fetch(url,{headers:{"user-agent":"HotelVastuImageMigration/2.0"}});
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

console.log("Image migration complete.");