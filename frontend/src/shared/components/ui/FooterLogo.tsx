import LogoImage from '/new_logo.png';

function FooterLogo() {
  return (
    <div className="flex items-center gap-3">
      <img src={LogoImage} alt="Alumni Portal" className="h-16 w-auto flex-none object-contain" />
      <span className="font-sans text-xl font-semibold uppercase tracking-wide text-white">
        Alumni Portal
      </span>
    </div>
  );
}

export default FooterLogo;
