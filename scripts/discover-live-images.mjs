const url="https://hotelvastu.com/assets/index-IDoo_XgH.js";
const r=await fetch(url);
const t=await r.text();

const files=[
"classic-banner.jpg","classic-room.jpg","classic-room-1.jpg","classic-room-2.jpg",
"club-banner.jpg","club-room.jpg","club-room-1.jpg","club-room-2.jpg",
"premium-banner.jpg","premium-room.jpg","premium-room-1.jpg","premium-room-2.jpg"
];

function esc(s){return s.replace(/[.*+?^$()|[\]\\]/g,"\\$&")}

for(const file of files){
  const key="../../assets/images/room/"+file;
  const re=new RegExp('"'+esc(key)+'":([A-Za-z_$][A-Za-z0-9_$]*)');
  const m=t.match(re);
  console.log("\nFILE",file,"VAR",m?.[1]||"NOT_FOUND");
  if(!m)continue;
  const v=m[1];
  const patterns=[
    new RegExp(esc(v)+"\\s*=\\s*`([^`]+)`"),
    new RegExp(esc(v)+'\\s*=\\s*"([^"]+)"'),
    new RegExp(esc(v)+"\\s*=\\s*'([^']+)'")
  ];
  let found=null;
  for(const p of patterns){const x=t.match(p);if(x){found=x[1];break}}
  console.log("DEPLOYED",found||"NOT_FOUND");
  if(!found){
    let pos=0,count=0;
    while((pos=t.indexOf(v,pos))>=0&&count<8){
      console.log("CTX",t.slice(Math.max(0,pos-180),Math.min(t.length,pos+300)).replace(/\s+/g," "));
      pos+=v.length;count++;
    }
  }
}
