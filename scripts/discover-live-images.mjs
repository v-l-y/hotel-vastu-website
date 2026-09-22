const pages=[
  "https://hotelvastu.com/",
  "https://hotelvastu.com/room/classic-room"
];

const seen=new Set();
const fetchedAssets=new Set();

function abs(raw,base){
  try{return new URL(raw,base).href}catch{return null}
}
function uniquePush(arr,value){
  if(value&&!seen.has(value)){seen.add(value);arr.push(value)}
}
function collectFromText(text,base){
  const out=[];
  const patterns=[
    /https?:\/\/[^"'\s)<>{}]+/gi,
    /["'](\/[^"'\s]+\.(?:avif|webp|jpe?g|png|gif|svg)(?:\?[^"']*)?)["']/gi,
    /["']([^"'\s]+\.(?:avif|webp|jpe?g|png|gif)(?:\?[^"']*)?)["']/gi,
    /["'](\/[^"'\s]*(?:api|media|upload|storage|image)[^"'\s]*)["']/gi
  ];
  for(const re of patterns){
    for(const m of text.matchAll(re)){
      const raw=m[1]||m[0];
      const value=abs(raw.replace(/[),;]+$/,""),base);
      if(value&&/^https?:/i.test(value)) uniquePush(out,value);
    }
  }
  return out;
}

for(const page of pages){
  const res=await fetch(page,{headers:{"user-agent":"Mozilla/5.0 HotelVastuMigration/2.0"}});
  console.log("\nPAGE",page,"STATUS",res.status);
  const html=await res.text();
  console.log("HTML_LENGTH",html.length);

  const assetUrls=[];
  for(const m of html.matchAll(/<(?:script|link)\b[^>]*(?:src|href)=["']([^"']+)["'][^>]*>/gi)){
    uniquePush(assetUrls,abs(m[1],page));
  }
  console.log("PAGE_ASSETS");
  assetUrls.forEach(u=>console.log(" ASSET",u));

  const htmlCandidates=collectFromText(html,page);
  console.log("HTML_CANDIDATES");
  htmlCandidates.forEach(u=>console.log(" CANDIDATE",u));

  for(const asset of assetUrls){
    if(fetchedAssets.has(asset)) continue;
    fetchedAssets.add(asset);
    if(!/^https?:\/\/hotelvastu\.com\//i.test(asset)) continue;
    if(!/\.(?:js|css)(?:\?|$)/i.test(asset)) continue;
    try{
      const ar=await fetch(asset,{headers:{"user-agent":"Mozilla/5.0 HotelVastuMigration/2.0"}});
      const text=await ar.text();
      console.log("\nFETCH_ASSET",asset,"STATUS",ar.status,"LENGTH",text.length);
      const candidates=collectFromText(text,asset)
        .filter(u=>/\.(?:avif|webp|jpe?g|png|gif)(?:\?|$)/i.test(u)||/(?:\/api\/|media|upload|storage|image)/i.test(u));
      candidates.slice(0,120).forEach(u=>console.log(" BUNDLE_CANDIDATE",u));
    }catch(err){
      console.log("ASSET_FETCH_ERROR",asset,String(err));
    }
  }
}
