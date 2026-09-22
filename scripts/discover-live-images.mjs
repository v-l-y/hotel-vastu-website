const r=await fetch("https://hotelvastu.com/assets/index-IDoo_XgH.js");
const t=await r.text();
for(const needle of ["function qM","var qM","qM=","src:Lu","facility-CRAVCotO.jpg"]){
  console.log("\n###",needle);
  let p=0,c=0;
  while((p=t.indexOf(needle,p))>=0){
    c++; console.log("MATCH",c,"AT",p,t.slice(Math.max(0,p-1800),Math.min(t.length,p+5200)).replace(/\s+/g," "));
    p+=needle.length; if(c>=10)break;
  }
  if(!c)console.log("NONE");
}