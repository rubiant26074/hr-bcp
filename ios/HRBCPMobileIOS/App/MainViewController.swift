import UIKit
import WebKit

final class MainViewController: UIViewController, WKNavigationDelegate, WKUIDelegate {
    private let initialURL = URL(string: "https://hr.berkahcipta.co.id/m/login")!

    private lazy var webView: WKWebView = {
        let config = WKWebViewConfiguration()
        config.allowsInlineMediaPlayback = true
        config.defaultWebpagePreferences.preferredContentMode = .mobile

        let view = WKWebView(frame: .zero, configuration: config)
        view.navigationDelegate = self
        view.uiDelegate = self
        view.allowsBackForwardNavigationGestures = true
        view.translatesAutoresizingMaskIntoConstraints = false
        return view
    }()

    private let loading = UIActivityIndicatorView(style: .large)
    private let nativeAttendanceButton: UIButton = {
        let button = UIButton(type: .system)
        button.setTitle("Absen Native", for: .normal)
        button.titleLabel?.font = .boldSystemFont(ofSize: 16)
        button.backgroundColor = UIColor(red: 29/255, green: 78/255, blue: 216/255, alpha: 1)
        button.setTitleColor(.white, for: .normal)
        button.layer.cornerRadius = 12
        button.contentEdgeInsets = UIEdgeInsets(top: 12, left: 18, bottom: 12, right: 18)
        button.translatesAutoresizingMaskIntoConstraints = false
        button.isHidden = true
        return button
    }()

    override func viewDidLoad() {
        super.viewDidLoad()
        title = "HR-BCP Mobile"
        view.backgroundColor = .systemBackground

        setupLayout()
        nativeAttendanceButton.addTarget(self, action: #selector(openNativeAttendance), for: .touchUpInside)
        webView.load(URLRequest(url: initialURL))
    }

    private func setupLayout() {
        view.addSubview(webView)
        view.addSubview(loading)
        view.addSubview(nativeAttendanceButton)

        loading.translatesAutoresizingMaskIntoConstraints = false
        loading.hidesWhenStopped = true

        NSLayoutConstraint.activate([
            webView.topAnchor.constraint(equalTo: view.safeAreaLayoutGuide.topAnchor),
            webView.leadingAnchor.constraint(equalTo: view.leadingAnchor),
            webView.trailingAnchor.constraint(equalTo: view.trailingAnchor),
            webView.bottomAnchor.constraint(equalTo: view.bottomAnchor),

            loading.centerXAnchor.constraint(equalTo: view.centerXAnchor),
            loading.centerYAnchor.constraint(equalTo: view.centerYAnchor),

            nativeAttendanceButton.trailingAnchor.constraint(equalTo: view.safeAreaLayoutGuide.trailingAnchor, constant: -16),
            nativeAttendanceButton.bottomAnchor.constraint(equalTo: view.safeAreaLayoutGuide.bottomAnchor, constant: -16)
        ])
    }

    @objc private func openNativeAttendance() {
        let vc = FaceAttendanceViewController()
        vc.modalPresentationStyle = .fullScreen
        present(vc, animated: true)
    }

    func webView(_ webView: WKWebView, didStartProvisionalNavigation navigation: WKNavigation!) {
        loading.startAnimating()
    }

    func webView(_ webView: WKWebView, didFinish navigation: WKNavigation!) {
        loading.stopAnimating()
        let path = webView.url?.path ?? ""
        nativeAttendanceButton.isHidden = !(path == "/m/attendance" || path == "/attendance/mobile")
    }

    func webView(_ webView: WKWebView, didFail navigation: WKNavigation!, withError error: Error) {
        loading.stopAnimating()
    }

    func webView(_ webView: WKWebView, didFailProvisionalNavigation navigation: WKNavigation!, withError error: Error) {
        loading.stopAnimating()
    }

    func webView(
        _ webView: WKWebView,
        decidePolicyFor navigationAction: WKNavigationAction,
        decisionHandler: @escaping (WKNavigationActionPolicy) -> Void
    ) {
        guard let url = navigationAction.request.url else {
            decisionHandler(.allow)
            return
        }

        let path = url.path
        if (path == "/m/attendance" || path == "/attendance/mobile"), url.query?.contains("native=1") != true {
            var comp = URLComponents(url: url, resolvingAgainstBaseURL: false)
            var items = comp?.queryItems ?? []
            items.append(URLQueryItem(name: "native", value: "1"))
            comp?.queryItems = items
            if let target = comp?.url {
                webView.load(URLRequest(url: target))
                decisionHandler(.cancel)
                return
            }
        }

        if ["http", "https"].contains(url.scheme?.lowercased() ?? "") {
            decisionHandler(.allow)
            return
        }

        UIApplication.shared.open(url, options: [:], completionHandler: nil)
        decisionHandler(.cancel)
    }
}
