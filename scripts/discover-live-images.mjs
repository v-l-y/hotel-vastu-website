const r=await fetch("https://hotelvastu.com/assets/index-IDoo_XgH.js");
const t=await r.text();
for(const needle of ["logo","Logo","navbar","brand"]){
 console.log("\n###",needle);
 let p=0,c=0; const lower=t.toLowerCase(), n=needle.toLowerCase();
 while((p=lower.indexOf(n,p))>=0){
   c++; console.log("MATCH",c,"AT",p,t.slice(Math.max(0,p-900),Math.min(t.length,p+2200)).replace(/\s+/g," "));
   p+=needle.length; if(c>=20)break;
 }
}