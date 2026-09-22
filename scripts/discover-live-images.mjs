const url="https://hotelvastu.com/assets/index-IDoo_XgH.js";
const r=await fetch(url);
const t=await r.text();
const needles=["/facilities","Facilities","Restaurant","restaurant","Breakfast","Parking","24/7 Support"];
for(const needle of needles){
 console.log("\n###",needle);
 let p=0,c=0; const lower=t.toLowerCase(), n=needle.toLowerCase();
 while((p=lower.indexOf(n,p))>=0){
   c++;
   console.log("MATCH",c,"AT",p,t.slice(Math.max(0,p-1100),Math.min(t.length,p+2600)).replace(/\s+/g," "));
   p+=needle.length;
   if(c>=12)break;
 }
}