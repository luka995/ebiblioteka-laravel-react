import { BookOpen, LayoutDashboard, LogIn, ShieldCheck } from 'lucide-react'

function App() {
  return (
    <main className="app-placeholder">
      <div className="placeholder-card">
        <div className="placeholder-brand"><span><BookOpen size={19} /></span> eBiblioteka</div>
        <div className="placeholder-icon"><LayoutDashboard size={28} /></div>
        <p className="placeholder-kicker">REAKT APLIKACIJA</p>
        <h1>Prostor za vaš<br /><em>nalog.</em></h1>
        <p className="placeholder-copy">Prijava, radni prostor biblioteke i administratorski panel biće dostupni ovde u sledećoj fazi.</p>
        <div className="placeholder-status"><ShieldCheck size={16} /> Laravel API veza je pripremljena</div>
        <a className="placeholder-link" href="http://localhost:81/">Nazad na početnu stranicu <LogIn size={16} /></a>
      </div>
    </main>
  )
}

export default App
