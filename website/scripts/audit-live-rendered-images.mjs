import { chromium } from "playwright";

const pages=[
  ["/","home"],
  ["/room/classic-room","classic"],
  ["/room/club-room","club"],
  ["/room/premium-room","premium"],
  ["/facilities","facilities"],
  ["/about","about"],
  ["/contact","contact"],
  ["/restaurant","restaurant"]
];

const browser=await chromium.launch({headless:true});
const page=await browser.newPage({viewport:{width:1440,height:1000}});
for(const [path,label] of pages){
  const url=new URL(path,"https://hotelvastu.com").href;
  const res=await page.goto(url,{waitUntil:"networkidle",timeout:60000}).catch(()=>null);
  const images=await page.evaluate(()=>{
    const rows=[];
    for(const img of document.querySelectorAll("img")){
      const src=img.currentSrc||img.src;
      if(src && /^https:\/\/hotelvastu\.com\/assets\/.+\.(?:png|jpe?g|webp|avif)(?:\?|$)/i.test(src)){
        rows.push({src,alt:img.alt||"",width:img.naturalWidth||0,height:img.naturalHeight||0});
      }
    }
    for(const el of document.querySelectorAll("*")){
      const bg=getComputedStyle(el).backgroundImage;
      if(!bg||bg==="none")continue;
      for(const m of bg.matchAll(/url\(["']?([^"')]+)["']?\)/g)){
        const src=m[1];
        if(/^https:\/\/hotelvastu\.com\/assets\/.+\.(?:png|jpe?g|webp|avif)(?:\?|$)/i.test(src)){
          rows.push({src,alt:"[background]",width:0,height:0});
        }
      }
    }
    const bySrc=new Map();
    for(const row of rows)if(!bySrc.has(row.src))bySrc.set(row.src,row);
    return [...bySrc.values()];
  });
  console.log("PAGE_JSON "+JSON.stringify({label,url,status:res?.status?.()??null,images}));
}
await browser.close();
