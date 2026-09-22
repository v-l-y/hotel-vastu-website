const url="https://hotelvastu.com/assets/index-IDoo_XgH.js";
const r=await fetch(url);
const t=await r.text();
for(const needle of ["slug:`club-room`","slug:`premium-room`","slug:`classic-room`"]){
  const i=t.indexOf(needle);
  console.log("\n###",needle,"AT",i);
  if(i>=0)console.log(t.slice(Math.max(0,i-200),Math.min(t.length,i+4200)).replace(/\s+/g," "));
}
