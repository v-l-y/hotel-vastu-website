const url="https://hotelvastu.com/assets/index-IDoo_XgH.js";
const r=await fetch(url);
const t=await r.text();
const needles=["Classic Room","Club Room","Premium Room","Classic","Club","Premium","src:Td","src:Dd","src:U","[Td","[Dd","[U","Td,","Dd,","U,"];
for(const needle of needles){
  const lower=t.toLowerCase(), n=needle.toLowerCase();
  let p=0,c=0;
  console.log("\n###",needle);
  while((p=lower.indexOf(n,p))>=0){
    c++;
    console.log("MATCH",c,"AT",p, t.slice(Math.max(0,p-450),Math.min(t.length,p+850)).replace(/\s+/g," "));
    p+=needle.length;
    if(c>=12)break;
  }
  if(!c)console.log("NONE");
}
