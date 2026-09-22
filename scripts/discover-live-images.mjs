const url="https://hotelvastu.com/assets/index-IDoo_XgH.js";
const r=await fetch(url,{headers:{"user-agent":"Mozilla/5.0 HotelVastuAssetAudit/1.0"}});
const t=await r.text();
console.log("BUNDLE",r.status,t.length);
const keywords=["restaurant","dining","breakfast","facility","facilities","reception","lobby","parking","exterior","entrance","about-main","contact","banner"];
for(const k of keywords){
  console.log("\n### KEYWORD",k);
  const lower=t.toLowerCase();
  let p=0,c=0;
  while((p=lower.indexOf(k.toLowerCase(),p))>=0){
    c++;
    const s=t.slice(Math.max(0,p-420),Math.min(t.length,p+900)).replace(/\s+/g," ");
    if(s.includes("/assets/")||s.includes("assets/images")||s.includes("src:")) console.log("MATCH",c,"AT",p,s);
    p+=k.length;
    if(c>=18)break;
  }
  if(!c)console.log("NONE");
}
console.log("\n### DEPLOYED IMAGE CONSTANTS");
const re=/([A-Za-z_$][A-Za-z0-9_$]*)=\x60(\/assets\/[^\x60]+\.(?:webp|jpg|jpeg|png))\x60/gi;
const seen=new Set();
for(const m of t.matchAll(re)){
  const value=m[2];
  if(seen.has(value))continue;
  seen.add(value);
  if(/restaurant|dining|breakfast|facility|reception|lobby|parking|exterior|entrance|about|contact|banner/i.test(value)) console.log(m[1],value);
}