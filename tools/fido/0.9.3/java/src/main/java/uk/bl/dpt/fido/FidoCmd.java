/**
 * 
 */
package uk.bl.dpt.fido;

import java.io.FileNotFoundException;
import java.io.FileOutputStream;
import java.net.Authenticator;
import java.net.PasswordAuthentication;

import javax.xml.bind.JAXBException;

import org.python.core.PyInteger;
import org.python.core.PyObject;
import org.python.util.PythonInterpreter;

import uk.gov.nationalarchives.pronom.SignatureFileType;

/**
 * @author Andrew.Jackson@bl.uk
 *
 */
public class FidoCmd {

	/**
	 * Apache CLI
	 * http://commons.apache.org/cli/usage.html
	 * 
	 * @param args
	 * @throws JAXBException 
	 * @throws FileNotFoundException 
	 */
	public static void main(String[] args)  {
		downloadSigFile();
	}
	
	/**
	 * See Jython Integration
	 * http://jythonpodcast.hostjava.net/jythonbook/en/1.0/JythonAndJavaIntegration.html
	 */
	static void pythonInvoker() {
		PythonInterpreter interp = new PythonInterpreter();
        //interp.exec("import sys");
        //interp.exec("print sys");
        interp.set("a", new PyInteger(42));
        interp.exec("print a");
        interp.exec("x = 2+2");
        PyObject x = interp.get("x");
        System.out.println("x: " + x);
        // Attempt to load the formats script:"
        interp.execfile( FidoCmd.class.getResourceAsStream("formats.py"));
        PyObject po = interp.get("all_formats");
        System.out.println("FF:");
        /* PROBLEM: Can't integrate it as it is.
         * The barrier you are probably hitting is the method length limit in JVM
			bytecode. It is 65535 bytes max. Long methods, really long python
			lists or dictionaries defined in the source code, or anything like
			that will run up against this limit.
         */
	}
	
	static void downloadSigFile() {
		// To make java.net.URL cope with an authenticating proxy.
		// Apache HTTPClient does this automatically, but we're not using that here at the moment.
		String proxyUser = System.getProperty("http.proxyUser");
		if (proxyUser != null) {
            Authenticator.setDefault(
            		new ProxyAuth( proxyUser, System.getProperty("http.proxyPassword") ) );
		}
		
		SignatureFileType sigFile = SigFileUtils.getLatestSigFile().getFFSignatureFile();
		try {
			SigFileUtils.writeSigFileToOutputStream(sigFile, new FileOutputStream("signaturefile.xml"));
		} catch (FileNotFoundException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		} catch (JAXBException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}		
	}
	
	static class ProxyAuth extends Authenticator {
	    private PasswordAuthentication auth;

	    private ProxyAuth(String user, String password) {
	        auth = new PasswordAuthentication(user, password == null ? new char[]{} : password.toCharArray());
	    }

	    protected PasswordAuthentication getPasswordAuthentication() {
	        return auth;
	    }
	}


}
