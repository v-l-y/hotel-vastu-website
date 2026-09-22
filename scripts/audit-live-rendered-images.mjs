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
  console.log("\nPAGE",label,url,"status",res?.status?.()??"n/a");
  const images=await page.evaluate(()=>{
    const set=new Set();
    for(const img of document.querySelectorAll("img")){
      const src=img.currentSrc||img.src;
      if(src)set.add(src);
    }
    for(const el of document.querySelectorAll("*")){
      const bg=getComputedStyle(el).backgroundImage;
      if(bg&&bg!=="none"){
        for(const m of bg.matchAll(/url\(["']?([^"')]+)["']?\)/g)) set.add(m[1]);
      }
    }
    return [...set];
  });
  for(const src of images)console.log("IMG",src);
}
await browser.close();
