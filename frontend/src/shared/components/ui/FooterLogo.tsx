import LogoImage from '/logo.png';

function FooterLogo() {
  return (
    <div className="flex items-center gap-3">
      <img src={LogoImage} alt="Alumni Portal" className="h-15 w-15 object-contain" />
      <span className="font-sans text-xl font-semibold uppercase tracking-wide text-white">
        Alumni Portal
      </span>
    </div>
  );
}

export default FooterLogo;
