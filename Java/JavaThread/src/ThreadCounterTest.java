import static org.junit.Assert.*;

import org.junit.BeforeClass;
import org.junit.Test;


public class ThreadCounterTest {

	@BeforeClass
	public static void setUpBeforeClass() throws Exception {
	}

	@Test
	public void testThreadCounter() {
		Counter global=new Counter();
		Thread t1=new Thread(new ThreadCounter(1, global));
		t1.start();
		new Thread(new ThreadCounter(2, global)).start();
		new Thread(new ThreadCounter(3, global)).start();
		new Thread(new ThreadCounter(4, global)).start();
		new Thread(new ThreadCounter(5, global)).start();
		new Thread(new ThreadCounter(6, global)).start();
		try {
			t1.join();
		} catch (InterruptedException e) {
			// TODO Auto-generated catch block
			e.printStackTrace();
		}
	}

}
