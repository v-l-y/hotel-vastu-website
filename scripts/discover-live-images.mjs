const pages=[
  "https://hotelvastu.com/",
  "https://hotelvastu.com/room/classic-room"
];

const seen=new Set();

function absolutize(raw,base){
  try{return new URL(raw,base).href}catch{return null}
}

for(const page of pages){
  const res=await fetch(page,{headers:{"user-agent":"Mozilla/5.0 HotelVastuMigration/1.0"}});
  console.log("\nPAGE",page,"STATUS",res.status);
  const html=await res.text();
  const urls=[];

  for(const match of html.matchAll(/<(?:img|source)\b[^>]*(?:src|srcset)=["']([^"']+)["'][^>]*>/gi)){
    const value=match[1];
    for(const part of value.split(",")){
      const raw=part.trim().split(/\s+/)[0];
      const abs=absolutize(raw,page);
      if(abs&&!seen.has(abs)){seen.add(abs);urls.push(abs)}
    }
  }

  for(const match of html.matchAll(/url\((?:["']?)([^)"']+)(?:["']?)\)/gi)){
    const abs=absolutize(match[1].trim(),page);
    if(abs&&!seen.has(abs)){seen.add(abs);urls.push(abs)}
  }

  const likelyImages=urls.filter(u=>/\.(?:avif|webp|jpe?g|png|gif|svg)(?:\?|$)/i.test(u));
  if(!likelyImages.length){
    console.log("NO_DIRECT_IMAGE_URLS_FOUND");
  }else{
    likelyImages.forEach((u,i)=>console.log(String(i+1).padStart(2,"0"),u));
  }
}
